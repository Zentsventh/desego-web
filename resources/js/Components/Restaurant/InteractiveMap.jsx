import React, { useEffect, useRef, useState } from 'react';
import { MapPin, Navigation, Crosshair, Check, AlertTriangle } from 'lucide-react';
import { money } from './ShopParts';

let leafletPromise = null;

function loadLeaflet() {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('SSR'));
    }
    if (window.L) {
        return Promise.resolve(window.L);
    }
    if (leafletPromise) {
        return leafletPromise;
    }

    leafletPromise = new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-leaflet-css]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            link.setAttribute('data-leaflet-css', '1');
            document.head.appendChild(link);
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        script.onload = () => {
            if (window.L) {
                resolve(window.L);
            } else {
                reject(new Error('Leaflet no disponible'));
            }
        };
        script.onerror = () => reject(new Error('No se pudo cargar el mapa interactivo'));
        document.head.appendChild(script);
    });

    return leafletPromise;
}

function haversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
}

function pointInPolygon(latitude, longitude, polygon) {
    if (!Array.isArray(polygon) || polygon.length < 3) {
        return false;
    }

    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const current = polygon[i];
        const previous = polygon[j];
        if (!Array.isArray(current) || !Array.isArray(previous) || current.length < 2 || previous.length < 2) {
            continue;
        }

        const [currentLat, currentLng] = current.map(Number);
        const [previousLat, previousLng] = previous.map(Number);
        const crossesLatitude = (currentLat > latitude) !== (previousLat > latitude);
        const crossingLongitude =
            ((previousLng - currentLng) * (latitude - currentLat)) /
                ((previousLat - currentLat) || Number.EPSILON) +
            currentLng;

        if (crossesLatitude && longitude < crossingLongitude) {
            inside = !inside;
        }
    }

    return inside;
}

export default function InteractiveMap({
    mode = 'restaurant',
    restaurantLat = -12.0125,
    restaurantLng = -76.8285,
    restaurantName = 'DeSego Cevichería',
    restaurantAddress = 'UCV 19, Lote 13, Zona A, Huaycán, Ate',
    latitude = null,
    longitude = null,
    deliveryZones = [],
    onLocationChange,
    onZoneDetected,
    height = 260,
}) {
    const containerRef = useRef(null);
    const mapInstanceRef = useRef(null);
    const markerRef = useRef(null);
    const [mapReady, setMapReady] = useState(false);
    const [mapFailed, setMapFailed] = useState(false);
    const [coverageInfo, setCoverageInfo] = useState(null);
    const [locating, setLocating] = useState(false);
    const [locationError, setLocationError] = useState('');
    const coverageRequestRef = useRef(null);

    const activeLat =
        mode === 'delivery'
            ? Number(latitude ?? restaurantLat)
            : Number(restaurantLat);
    const activeLng =
        mode === 'delivery'
            ? Number(longitude ?? restaurantLng)
            : Number(restaurantLng);

    const verifyCoverage = async (lat, lng) => {
        if (mode !== 'delivery') {
            return;
        }

        coverageRequestRef.current?.abort();
        const controller = new AbortController();
        coverageRequestRef.current = controller;

        try {
            const res = await fetch(
                `/api/storefront?view=coverage&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
                { headers: { Accept: 'application/json' }, signal: controller.signal, cache: 'no-store' }
            );
            if (!res.ok) {
                throw new Error(`No se pudo consultar la cobertura (${res.status}).`);
            }
            const json = await res.json();
            if (json?.ok && typeof json.covered === 'boolean') {
                const zone = json.zone || json.matched_zone || null;
                const covered = Boolean(json.covered && zone?.id);
                setCoverageInfo({
                    covered,
                    zone: covered ? zone : null,
                    message: json.message || '',
                });
                onZoneDetected?.(covered ? zone : null);
                return;
            }
            throw new Error('La respuesta de cobertura no es válida.');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
        }

        let matched = null;
        for (const z of deliveryZones) {
            if (Array.isArray(z.polygon_coordinates) && pointInPolygon(lat, lng, z.polygon_coordinates)) {
                matched = z;
                break;
            }
            if (z.center_latitude != null && z.center_longitude != null && Number(z.radius_km) > 0) {
                const d = haversineKm(
                    lat,
                    lng,
                    Number(z.center_latitude),
                    Number(z.center_longitude)
                );
                if (d <= Number(z.radius_km) * 1.15) {
                    matched = z;
                    break;
                }
            }
        }
        setCoverageInfo({
            covered: Boolean(matched),
            zone: matched,
            message: matched
                ? `Dentro de cobertura: ${matched.name}`
                : 'Ubicación fuera de nuestras zonas habituales de reparto.',
        });
        onZoneDetected?.(matched);
    };

    useEffect(() => {
        let cancelled = false;
        let resizeTimer;

        loadLeaflet()
            .then((L) => {
                if (cancelled || !containerRef.current) {
                    return;
                }
                if (mapInstanceRef.current) {
                    mapInstanceRef.current.remove();
                    mapInstanceRef.current = null;
                }

                const map = L.map(containerRef.current, {
                    center: [activeLat, activeLng],
                    zoom: mode === 'restaurant' ? 16 : 14,
                    scrollWheelZoom: false,
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map);

                const pinIcon = L.divIcon({
                    className: 'dg-leaflet-pin',
                    html: `<div style="width:32px;height:32px;border-radius:50% 50% 50% 0;background:#004797;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 16px rgba(0,71,151,0.45);border:2px solid #fff;"><span style="width:10px;height:10px;border-radius:50%;background:#fff;transform:rotate(45deg);"></span></div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -28],
                });

                if (mode === 'restaurant') {
                    const marker = L.marker([restaurantLat, restaurantLng], {
                        icon: pinIcon,
                    }).addTo(map);
                    marker.bindPopup(
                        `<div style="font-family:system-ui,sans-serif;min-width:180px;"><strong style="color:#004797;font-size:13px;">${restaurantName}</strong><p style="margin:4px 0 0;font-size:12px;color:#334155;">${restaurantAddress}</p></div>`
                    );
                    markerRef.current = marker;
                } else {
                    deliveryZones.forEach((z) => {
                        if (Array.isArray(z.polygon_coordinates) && z.polygon_coordinates.length >= 3) {
                            L.polygon(z.polygon_coordinates, {
                                color: '#004797',
                                weight: 1.5,
                                fillColor: '#004797',
                                fillOpacity: 0.08,
                            })
                                .bindTooltip(z.name)
                                .addTo(map);
                        } else if (z.center_latitude && z.center_longitude && z.radius_km) {
                            L.circle([Number(z.center_latitude), Number(z.center_longitude)], {
                                radius: Number(z.radius_km) * 1000,
                                color: '#004797',
                                weight: 1.5,
                                fillColor: '#004797',
                                fillOpacity: 0.07,
                            })
                                .bindTooltip(z.name)
                                .addTo(map);
                        }
                    });

                    const marker = L.marker([activeLat, activeLng], {
                        icon: pinIcon,
                        draggable: true,
                    }).addTo(map);

                    marker.on('dragend', () => {
                        const pos = marker.getLatLng();
                        const lat = Number(pos.lat.toFixed(6));
                        const lng = Number(pos.lng.toFixed(6));
                        onLocationChange?.(lat, lng);
                        verifyCoverage(lat, lng);
                    });

                    map.on('click', (ev) => {
                        const lat = Number(ev.latlng.lat.toFixed(6));
                        const lng = Number(ev.latlng.lng.toFixed(6));
                        marker.setLatLng([lat, lng]);
                        onLocationChange?.(lat, lng);
                        verifyCoverage(lat, lng);
                    });

                    markerRef.current = marker;
                    if (latitude != null && longitude != null) {
                        verifyCoverage(Number(latitude), Number(longitude));
                    }
                }

                mapInstanceRef.current = map;
                setMapReady(true);
                resizeTimer = setTimeout(() => {
                    if (!cancelled) {
                        map.invalidateSize();
                    }
                }, 180);
            })
            .catch(() => {
                if (!cancelled) {
                    setMapFailed(true);
                }
            });

        return () => {
            cancelled = true;
            clearTimeout(resizeTimer);
            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }
            coverageRequestRef.current?.abort();
        };
    }, [mode, restaurantLat, restaurantLng, deliveryZones.length]);

    useEffect(() => {
        if (
            mode === 'delivery' &&
            mapReady &&
            markerRef.current &&
            mapInstanceRef.current &&
            latitude != null &&
            longitude != null
        ) {
            const lat = Number(latitude);
            const lng = Number(longitude);
            markerRef.current.setLatLng([lat, lng]);
            mapInstanceRef.current.panTo([lat, lng]);
            verifyCoverage(lat, lng);
        }
    }, [latitude, longitude, mapReady, mode]);

    const handleUseMyLocation = () => {
        if (!navigator.geolocation) {
            setLocationError('Este dispositivo o navegador no permite acceder al GPS.');
            return;
        }
        setLocationError('');
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setLocating(false);
                const lat = Number(pos.coords.latitude.toFixed(6));
                const lng = Number(pos.coords.longitude.toFixed(6));
                if (markerRef.current && mapInstanceRef.current) {
                    markerRef.current.setLatLng([lat, lng]);
                    mapInstanceRef.current.setView([lat, lng], 16);
                }
                onLocationChange?.(lat, lng);
                verifyCoverage(lat, lng);
            },
            (error) => {
                setLocating(false);
                setLocationError(
                    error.code === error.PERMISSION_DENIED
                        ? 'Permite el acceso a tu ubicación en el navegador para usar el GPS.'
                        : error.code === error.POSITION_UNAVAILABLE
                          ? 'No se pudo determinar tu ubicación. Comprueba que el GPS esté activado.'
                          : 'La búsqueda de ubicación tardó demasiado. Inténtalo nuevamente.'
                );
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
        );
    };

    const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${restaurantLat},${restaurantLng}`;
    const wazeUrl = `https://waze.com/ul?ll=${restaurantLat},${restaurantLng}&navigate=yes`;

    return (
        <div
            style={{
                borderRadius: '14px',
                overflow: 'hidden',
                border: '1px solid #dce3ee',
                background: '#f8fafc',
            }}
        >
            {mode === 'delivery' && (
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '8px',
                        padding: '8px 12px',
                        background: '#eff4fb',
                        borderBottom: '1px solid #dce3ee',
                        fontSize: '12px',
                    }}
                >
                    <span style={{ color: '#1e293b', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '5px' }}>
                        <MapPin size={14} color="#004797" />
                        Haz clic en el mapa o arrastra el pin a tu puerta
                    </span>
                    <button
                        type="button"
                        onClick={handleUseMyLocation}
                        disabled={locating}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '5px',
                            padding: '5px 10px',
                            borderRadius: '8px',
                            border: 'none',
                            background: '#004797',
                            color: '#fff',
                            fontSize: '11px',
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        <Crosshair size={13} />
                        {locating ? 'Ubicando...' : 'Usar mi GPS'}
                    </button>
                </div>
            )}
            {mode === 'delivery' && locationError && (
                <p role="alert" style={{ margin: 0, padding: '8px 12px', color: '#b42318', background: '#fff1f0', fontSize: '12px' }}>
                    {locationError}
                </p>
            )}

            {mapFailed ? (
                <div style={{ padding: '18px', fontSize: '12px', color: '#475569' }}>
                    <strong>{restaurantName}</strong> · {restaurantAddress}
                    {mode === 'delivery' && (
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', marginTop: '10px' }}>
                            <label style={{ fontSize: '11px' }}>
                                Latitud
                                <input
                                    type="number"
                                    step="0.000001"
                                    value={latitude ?? ''}
                                    onChange={(e) => {
                                        const v = Number(e.target.value);
                                        onLocationChange?.(v, Number(longitude || restaurantLng));
                                    }}
                                />
                            </label>
                            <label style={{ fontSize: '11px' }}>
                                Longitud
                                <input
                                    type="number"
                                    step="0.000001"
                                    value={longitude ?? ''}
                                    onChange={(e) => {
                                        const v = Number(e.target.value);
                                        onLocationChange?.(Number(latitude || restaurantLat), v);
                                    }}
                                />
                            </label>
                        </div>
                    )}
                </div>
            ) : (
                <div
                    ref={containerRef}
                    style={{ width: '100%', height: `${height}px`, zIndex: 1 }}
                    aria-label={
                        mode === 'restaurant'
                            ? 'Mapa de ubicación de DeSego Cevichería en Huaycán'
                            : 'Mapa interactivo de cobertura de reparto'
                    }
                />
            )}

            {mode === 'delivery' && coverageInfo && (
                <div
                    role="status"
                    style={{
                        padding: '9px 12px',
                        fontSize: '12px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '8px',
                        background: coverageInfo.covered ? '#ecfdf5' : '#fffbeb',
                        color: coverageInfo.covered ? '#065f46' : '#92400e',
                        borderTop: '1px solid #dce3ee',
                    }}
                >
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontWeight: 600 }}>
                        {coverageInfo.covered ? <Check size={15} /> : <AlertTriangle size={15} />}
                        {coverageInfo.covered
                            ? `Cobertura confirmada: ${coverageInfo.zone?.name || coverageInfo.zone?.district || 'tu dirección'}`
                            : 'Fuera de cobertura directa — coordina por WhatsApp antes de confirmar'}
                    </span>
                    {coverageInfo.covered && coverageInfo.zone && (
                        <span style={{ fontSize: '11px', fontWeight: 600 }}>
                            {coverageInfo.zone.fee_confirmed
                                ? `Envío: ${money(coverageInfo.zone.delivery_fee)}`
                                : 'Envío: Por coordinar'}
                        </span>
                    )}
                </div>
            )}

            {mode === 'restaurant' && (
                <div
                    style={{
                        padding: '10px 14px',
                        display: 'flex',
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '10px',
                        background: '#ffffff',
                        borderTop: '1px solid #e2e8f0',
                        fontSize: '12px',
                    }}
                >
                    <span style={{ color: '#334155' }}>
                        📍 <strong>{restaurantAddress}</strong> (CP 15479)
                    </span>
                    <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                        <a
                            href={googleMapsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '5px',
                                padding: '6px 12px',
                                borderRadius: '8px',
                                background: '#004797',
                                color: '#fff',
                                fontWeight: 600,
                                textDecoration: 'none',
                            }}
                        >
                            <Navigation size={13} />
                            Cómo llegar (Google Maps)
                        </a>
                        <a
                            href={wazeUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '5px',
                                padding: '6px 12px',
                                borderRadius: '8px',
                                background: '#eef2f8',
                                color: '#004797',
                                fontWeight: 600,
                                textDecoration: 'none',
                            }}
                        >
                            Abrir en Waze
                        </a>
                    </div>
                </div>
            )}
        </div>
    );
}
