import ReservationForm from '../../Components/Restaurant/ReservationForm';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ArrowRight,
    ShoppingBag,
    Plus,
    Minus,
    X,
    Search,
    Fish,
    Waves,
    MapPin,
    Check,
    UserRound,
    Heart,
    BrandWhatsapp,
    Menu,
    Clock3,
    Sparkles,
    ShieldCheck,
    Star,
    ThumbsUp,
    Truck,
} from '../../Components/Restaurant/Icons';
import { useCart } from '../../hooks/useCart';
import { Checkout, foodImage, money } from '../../Components/Restaurant/ShopParts';
import InteractiveMap from '../../Components/Restaurant/InteractiveMap';
import ChatBot from '../../Components/Restaurant/ChatBot';
import '../../../css/restaurant.css';
import '../../../css/restaurant-mobile.css';
import '../../../css/restaurant-immersive.css';

const DAY_SCHEMA = {
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
    7: 'Sunday',
};

function currentLimaIsoDay() {
    try {
        const short = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Lima',
            weekday: 'short',
        }).format(new Date());
        const map = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };
        return map[short] || 1;
    } catch {
        const d = new Date().getDay();
        return d === 0 ? 7 : d;
    }
}

/** Escena de ingredientes renderizada en WebGL; el contenido permanece en HTML. */
function OceanScene({ vivid, paused }) {
    const canvasRef = useRef(null);
    const palette = useRef(vivid);
    const motionPaused = useRef(paused);
    const invalidate = useRef(() => {});
    useEffect(() => {
        palette.current = vivid;
        motionPaused.current = paused;
        invalidate.current();
    }, [vivid, paused]);
    useEffect(() => {
        const canvas = canvasRef.current;
        const gl = canvas.getContext('webgl', {
            alpha: false,
            antialias: false,
            powerPreference: 'low-power',
        });
        if (!gl) {
            return;
        }
        const vertexSource =
            'attribute vec2 a; void main(){gl_Position=vec4(a,0.,1.);}';
        const fragmentSource = `
            precision mediump float;
            uniform vec2 resolution;
            uniform vec2 pointer;
            uniform float time;
            uniform float palette;
            mat2 rot(float a){return mat2(cos(a),-sin(a),sin(a),cos(a));}
            vec2 scene(vec3 p){
                vec2 hit=vec2(20.,0.);
                for(int i=0;i<7;i++){
                    float f=float(i);
                    float angle=f*2.399+time*.09;
                    vec3 center=vec3(cos(angle)*(2.2+mod(f,2.)*.7),sin(angle*.8+f)*1.4, sin(angle)*1.2);
                    center.xy+=pointer*.25;
                    vec3 q=p-center;
                    q.xy=rot(time*.12+f)*q.xy;
                    q.yz=rot(f*.7)*q.yz;
                    float radius=.30+mod(f,3.)*.09;
                    float d;
                    float material;
                    if(mod(f,3.)<.5){
                        vec3 b=abs(q)-vec3(radius*.85);
                        d=length(max(b,0.))+min(max(b.x,max(b.y,b.z)),0.)-.09;
                        material=2.;
                    }else{
                        q.y*=1.15;
                        d=length(q)-radius;
                        d+=sin(q.x*90.)*sin(q.y*90.)*sin(q.z*90.)*.002;
                        material=mod(f,2.)<.5?1.:3.;
                    }
                    if(d<hit.x)hit=vec2(d,material);
                }
                return hit;
            }
            void main(){
                vec2 uv=(gl_FragCoord.xy-.5*resolution)/resolution.y;
                vec3 ro=vec3(0.,0.,7.5);
                vec3 rd=normalize(vec3(uv,-1.75));
                ro.xy+=pointer*.16;
                vec3 bg=mix(vec3(.045,.06,.09),vec3(.025,.09,.19),palette);
                vec3 color=bg+vec3(.04,.045,.06)*max(0.,1.-length(uv)*.7);
                float t=0.; vec2 hit;
                for(int step=0;step<48;step++){
                    hit=scene(ro+rd*t);
                    if(hit.x<.008||t>13.)break;
                    t+=hit.x*.85;
                }
                if(t<13.&&hit.x<.02){
                    vec3 p=ro+rd*t;
                    vec2 e=vec2(.008,0.);
                    vec3 n=normalize(vec3(scene(p+e.xyy).x-scene(p-e.xyy).x,scene(p+e.yxy).x-scene(p-e.yxy).x,scene(p+e.yyx).x-scene(p-e.yyx).x));
                    vec3 light=normalize(vec3(-3.,5.,5.));
                    float diffuse=max(dot(n,light),0.);
                    float rim=pow(1.-max(dot(n,-rd),0.),3.);
                    float spec=pow(max(dot(reflect(-light,n),-rd),0.),45.);
                    vec3 base=hit.y<1.5?mix(vec3(.16,.23,.96),vec3(.64,.80,.12),palette):hit.y<2.5?vec3(.76,.82,.87):vec3(.48,.65,.12);
                    color=base*(.23+diffuse*.85)+vec3(spec*.8)+vec3(.35,.45,.63)*rim*.5;
                    color=mix(color,bg,smoothstep(8.,14.,t)*.5);
                }
                color+=sin(gl_FragCoord.x*1.7+gl_FragCoord.y*.9)*.002;
                gl_FragColor=vec4(pow(color,vec3(.92)),1.);
            }`;
        const shaders = [];
        function compile(type, source) {
            const shader = gl.createShader(type);
            gl.shaderSource(shader, source);
            gl.compileShader(shader);
            shaders.push(shader);
            return gl.getShaderParameter(shader, gl.COMPILE_STATUS)
                ? shader
                : null;
        }
        const vertex = compile(gl.VERTEX_SHADER, vertexSource);
        const fragment = compile(gl.FRAGMENT_SHADER, fragmentSource);
        if (!vertex || !fragment) {
            shaders.forEach((shader) => gl.deleteShader(shader));
            return;
        }
        const program = gl.createProgram();
        gl.attachShader(program, vertex);
        gl.attachShader(program, fragment);
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            gl.deleteProgram(program);
            shaders.forEach((shader) => gl.deleteShader(shader));
            return;
        }
        gl.useProgram(program);
        const buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]),
            gl.STATIC_DRAW
        );
        const attribute = gl.getAttribLocation(program, 'a');
        gl.enableVertexAttribArray(attribute);
        gl.vertexAttribPointer(attribute, 2, gl.FLOAT, false, 0, 0);
        const locations = Object.fromEntries(
            ['resolution', 'pointer', 'time', 'palette'].map((name) => [
                name,
                gl.getUniformLocation(program, name),
            ])
        );
        const mouse = { x: 0, y: 0 };
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let frame = 0,
            visible = true,
            disposed = false,
            sceneTime = 0,
            previousTime = 0;
        function resize() {
            const rect = canvas.getBoundingClientRect();
            const scale = Math.min(
                window.devicePixelRatio || 1,
                1.25,
                1100 / Math.max(rect.width, 1)
            );
            canvas.width = Math.max(1, Math.round(rect.width * scale));
            canvas.height = Math.max(1, Math.round(rect.height * scale));
            gl.viewport(0, 0, canvas.width, canvas.height);
            start();
        }
        function move(event) {
            const rect = canvas.getBoundingClientRect();
            mouse.x = (event.clientX - rect.left) / rect.width - 0.5;
            mouse.y = 0.5 - (event.clientY - rect.top) / rect.height;
        }
        function render(now) {
            frame = 0;
            if (disposed || !visible || document.hidden) {
                return;
            }
            if (!motionPaused.current && !reduced.matches) {
                sceneTime += Math.min((now - previousTime) * 0.001, 0.05);
            }
            previousTime = now;
            gl.uniform2f(locations.resolution, canvas.width, canvas.height);
            gl.uniform2f(
                locations.pointer,
                reduced.matches || motionPaused.current ? 0 : mouse.x,
                reduced.matches || motionPaused.current ? 0 : mouse.y
            );
            gl.uniform1f(locations.time, sceneTime);
            gl.uniform1f(locations.palette, palette.current ? 1 : 0);
            gl.drawArrays(gl.TRIANGLES, 0, 6);
            if (!motionPaused.current && !reduced.matches) {
                frame = requestAnimationFrame(render);
            }
        }
        function start() {
            if (!frame && !disposed && visible && !document.hidden) {
                frame = requestAnimationFrame(render);
            }
        }
        invalidate.current = start;
        const resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(canvas);
        const observer = new IntersectionObserver((entries) => {
            visible = entries[0].isIntersecting;
            if (visible) {
                start();
            } else {
                cancelAnimationFrame(frame);
                frame = 0;
            }
        });
        observer.observe(canvas);
        canvas.parentElement.addEventListener('pointermove', move);
        document.addEventListener('visibilitychange', start);
        resize();
        start();
        return () => {
            disposed = true;
            invalidate.current = () => {};
            cancelAnimationFrame(frame);
            resizeObserver.disconnect();
            observer.disconnect();
            canvas.parentElement?.removeEventListener('pointermove', move);
            document.removeEventListener('visibilitychange', start);
            gl.deleteBuffer(buffer);
            gl.deleteProgram(program);
            shaders.forEach((shader) => gl.deleteShader(shader));
        };
    }, []);
    return <canvas ref={canvasRef} className="im-canvas" aria-hidden="true" />;
}

export default function Store({ catalog, lastOrder }) {
    const { auth } = usePage().props;
    const {
        products = [],
        categories: catalogCategories = [],
        branch,
        delivery_fee: fee = 0,
        social = {},
        business_hours: businessHours = [],
        promotions = [],
        is_open_now: isOpenNow = true,
        favorite_ids: serverFavorites = [],
        customer_profile: customerProfile = null,
        customer_addresses: customerAddresses = [],
        payment_methods: paymentMethods = ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'],
        services = {},
    } = catalog || {};

    const cart = useCart(products);
    const [cartOpen, setCartOpen] = useState(false);
    const [navigationOpen, setNavigationOpen] = useState(false);
    const [mode, setMode] = useState('Delivery');
    const [category, setCategory] = useState('all');
    const [sortBy, setSortBy] = useState('default');
    const [onlyFavorites, setOnlyFavorites] = useState(false);
    const [query, setQuery] = useState('');
    const [favorites, setFavorites] = useState(() => {
        if (serverFavorites?.length) {
            return serverFavorites.map(Number);
        }
        try {
            const raw = JSON.parse(localStorage.getItem('desego.favorites.v1') || '[]');
            return Array.isArray(raw) ? raw.map(Number) : [];
        } catch {
            return [];
        }
    });

    const [selectedProduct, setSelectedProduct] = useState(null);
    const [activeGalleryImg, setActiveGalleryImg] = useState('');
    const [detailQty, setDetailQty] = useState(1);
    const [detailNotes, setDetailNotes] = useState('');
    const [detailOptions, setDetailOptions] = useState([]);
    const [productReviews, setProductReviews] = useState([]);
    const [loadingReviews, setLoadingReviews] = useState(false);

    const [vivid, setVivid] = useState(false);
    const [scenePaused, setScenePaused] = useState(false);
    const [toast, setToast] = useState('');

    const detailDialog = useRef(null);
    const toastTimer = useRef(null);
    const stageRef = useRef(null);
    const rootRef = useRef(null);
    const navigationRef = useRef(null);
    const menuButtonRef = useRef(null);
    const todayIso = useMemo(() => currentLimaIsoDay(), []);

    useEffect(() => {
        if (serverFavorites?.length) {
            setFavorites(serverFavorites.map(Number));
        }
    }, [serverFavorites]);

    useEffect(() => {
        try {
            const reorderId = localStorage.getItem('desego.reorder_product');
            const openCartFlag = localStorage.getItem('desego.open_cart');
            if (reorderId) {
                localStorage.removeItem('desego.reorder_product');
                const found = products.find((p) => Number(p.id) === Number(reorderId));
                if (found && found.can_order !== false) {
                    cart.add(found.id, 1);
                    setCartOpen(true);
                }
            } else if (openCartFlag) {
                localStorage.removeItem('desego.open_cart');
                setCartOpen(true);
            }
        } catch {}
    }, [products]);

    function scrollToSection(sectionId, event) {
        if (event) {
            event.preventDefault();
        }
        setNavigationOpen(false);
        if (sectionId === 'inicio') {
            window.history.replaceState(null, '', window.location.pathname);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        const target = document.getElementById(sectionId);
        if (target) {
            window.history.replaceState(null, '', `#${sectionId}`);
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    useEffect(() => {
        const handleInitialOrHashScroll = () => {
            const rawHash = (window.location.hash || '').replace(/^#/, '').trim();
            if (!rawHash) {
                return;
            }
            const target = document.getElementById(rawHash);
            if (target) {
                requestAnimationFrame(() => {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        };

        const timer = setTimeout(handleInitialOrHashScroll, 80);
        window.addEventListener('hashchange', handleInitialOrHashScroll);
        return () => {
            clearTimeout(timer);
            window.removeEventListener('hashchange', handleInitialOrHashScroll);
        };
    }, []);

    const orderedProducts = useMemo(() => {
        const categoryRank = (v) =>
            /fresco/i.test(v || '')
                ? 0
                : /caliente/i.test(v || '')
                  ? 1
                  : /fritura/i.test(v || '')
                    ? 2
                    : /duo|dúo|trio|trío|ronda/i.test(v || '')
                      ? 3
                      : 4;
        const base = [...products].sort(
            (a, b) =>
                categoryRank(a.category) - categoryRank(b.category) ||
                (a.code && b.code
                    ? a.code.localeCompare(b.code, 'es')
                    : a.name.localeCompare(b.name, 'es'))
        );

        if (sortBy === 'price_asc') {
            return [...base].sort((a, b) => Number(a.price) - Number(b.price));
        }
        if (sortBy === 'price_desc') {
            return [...base].sort((a, b) => Number(b.price) - Number(a.price));
        }
        if (sortBy === 'rating') {
            return [...base].sort(
                (a, b) =>
                    Number(b.review?.average || 0) - Number(a.review?.average || 0) ||
                    Number(b.review?.count || 0) - Number(a.review?.count || 0)
            );
        }
        if (sortBy === 'featured') {
            return [...base].sort(
                (a, b) =>
                    Number(Boolean(b.is_featured)) - Number(Boolean(a.is_featured)) ||
                    Number(Boolean(b.is_popular)) - Number(Boolean(a.is_popular))
            );
        }
        return base;
    }, [products, sortBy]);

    const categories = useMemo(() => {
        const fromProducts = [...new Set(orderedProducts.map((p) => p.category).filter(Boolean))];
        const fromCatalog = (catalogCategories || []).map((c) => c.name).filter(Boolean);
        return [...new Set([...fromProducts, ...fromCatalog])];
    }, [orderedProducts, catalogCategories]);

    const categoryCounts = useMemo(() => {
        const map = {};
        products.forEach((p) => {
            if (p.category) {
                map[p.category] = (map[p.category] || 0) + 1;
            }
        });
        return map;
    }, [products]);

    const popularShowcase = useMemo(
        () => products.filter((p) => p.is_featured || p.is_popular).slice(0, 4),
        [products]
    );

    const filteredProducts = useMemo(() => {
        const q = query.trim().toLocaleLowerCase('es');
        return orderedProducts.filter((product) => {
            if (category !== 'all' && product.category !== category) {
                return false;
            }
            if (onlyFavorites && !favorites.includes(Number(product.id))) {
                return false;
            }
            if (!q) {
                return true;
            }
            return `${product.code || ''} ${product.name} ${product.description || ''} ${product.included_items || ''}`
                .toLocaleLowerCase('es')
                .includes(q);
        });
    }, [orderedProducts, category, onlyFavorites, favorites, query]);

    useEffect(() => () => clearTimeout(toastTimer.current), []);

    useEffect(() => {
        if (!navigationOpen) {
            return;
        }
        function dismissNavigation(event) {
            if (event.type === 'keydown' && event.key === 'Escape') {
                setNavigationOpen(false);
                menuButtonRef.current?.focus();
            }
            if (
                event.type === 'pointerdown' &&
                !navigationRef.current?.contains(event.target) &&
                !menuButtonRef.current?.contains(event.target)
            ) {
                setNavigationOpen(false);
            }
        }
        document.addEventListener('keydown', dismissNavigation);
        document.addEventListener('pointerdown', dismissNavigation);
        return () => {
            document.removeEventListener('keydown', dismissNavigation);
            document.removeEventListener('pointerdown', dismissNavigation);
        };
    }, [navigationOpen]);

    useEffect(() => {
        if (selectedProduct) {
            setActiveGalleryImg(foodImage(selectedProduct));
            setDetailQty(1);
            setDetailNotes('');
            const defaults = [];
            (selectedProduct.option_groups || []).forEach((grp) => {
                if (grp.is_required && grp.options?.length) {
                    defaults.push(Number(grp.options[0].id));
                }
            });
            setDetailOptions(defaults);

            if (!detailDialog.current.open) {
                detailDialog.current.showModal();
            }

            setLoadingReviews(true);
            fetch(`/productos/${selectedProduct.id}/resenas`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((json) => {
                    setProductReviews(json?.reviews || []);
                })
                .catch(() => setProductReviews([]))
                .finally(() => setLoadingReviews(false));
        } else if (detailDialog.current?.open) {
            detailDialog.current.close();
        }
    }, [selectedProduct]);

    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                }),
            { threshold: 0.08 }
        );
        rootRef.current
            ?.querySelectorAll('[data-reveal]')
            .forEach((element) => observer.observe(element));
        return () => observer.disconnect();
    }, []);

    function showToast(msg) {
        setToast(msg);
        clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setToast(''), 3200);
    }

    function addProduct(product, qty = 1, opts = [], notes = '') {
        if (product.can_order === false) {
            return;
        }
        cart.add(product.id, qty, opts, notes);
        showToast(`${product.name} se agregó a tu pedido.`);
    }

    function toggleFavorite(product, ev) {
        ev?.stopPropagation();
        const pid = Number(product.id);
        const exists = favorites.includes(pid);
        const next = exists
            ? favorites.filter((id) => id !== pid)
            : [...favorites, pid];
        setFavorites(next);
        try {
            localStorage.setItem('desego.favorites.v1', JSON.stringify(next));
        } catch {}

        if (auth?.user) {
            router.post(
                `/cuenta/favoritos/${pid}/toggle`,
                {},
                { preserveScroll: true, preserveState: true }
            );
        }
        showToast(
            exists
                ? `${product.name} se quitó de favoritos.`
                : `${product.name} guardado en tus favoritos.`
        );
    }

    function toggleDetailOption(grp, optId) {
        const idNum = Number(optId);
        const groupOptIds = (grp.options || []).map((o) => Number(o.id));
        if (Number(grp.max_selections || 1) === 1) {
            setDetailOptions((prev) => {
                const withoutGroup = prev.filter((id) => !groupOptIds.includes(id));
                if (prev.includes(idNum) && !grp.is_required) {
                    return withoutGroup;
                }
                return [...withoutGroup, idNum];
            });
        } else {
            setDetailOptions((prev) => {
                if (prev.includes(idNum)) {
                    return prev.filter((id) => id !== idNum);
                }
                const currentInGroup = prev.filter((id) => groupOptIds.includes(id));
                if (currentInGroup.length >= Number(grp.max_selections || 5)) {
                    return prev;
                }
                return [...prev, idNum];
            });
        }
    }

    function markReviewHelpful(reviewId) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch('/api/reviews', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ action: 'review.helpful', id: reviewId, csrf_token: csrf }),
        })
            .then((r) => r.json())
            .then((json) => {
                if (json?.ok) {
                    setProductReviews((prev) =>
                        prev.map((rv) =>
                            rv.id === reviewId
                                ? { ...rv, helpful_count: json.helpful_count ?? (rv.helpful_count || 0) + 1 }
                                : rv
                        )
                    );
                }
            })
            .catch(() => {});
    }

    function moveScene(event) {
        if (
            scenePaused ||
            event.pointerType !== 'mouse' ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        stageRef.current.style.setProperty(
            '--pointer-x',
            `${((event.clientX - rect.left) / rect.width - 0.5) * 18}deg`
        );
        stageRef.current.style.setProperty(
            '--pointer-y',
            `${((event.clientY - rect.top) / rect.height - 0.5) * -15}deg`
        );
    }

    function closeNavigation() {
        setNavigationOpen(false);
    }

    const detailGallery = useMemo(() => {
        if (!selectedProduct) {
            return [];
        }
        const main = foodImage(selectedProduct);
        const extras = (selectedProduct.gallery || [])
            .map((g) => (typeof g === 'string' ? g : g.image_url))
            .filter((u) => u && u !== main);
        return [main, ...extras];
    }, [selectedProduct]);

    const detailUnitPrice = useMemo(() => {
        if (!selectedProduct) {
            return 0;
        }
        let delta = 0;
        (selectedProduct.option_groups || []).forEach((grp) => {
            (grp.options || []).forEach((opt) => {
                if (detailOptions.includes(Number(opt.id))) {
                    delta += Number(opt.price_delta || 0);
                }
            });
        });
        return Number(selectedProduct.price) + delta;
    }, [selectedProduct, detailOptions]);

    const relatedProducts = useMemo(() => {
        if (!selectedProduct) {
            return [];
        }
        return orderedProducts
            .filter(
                (p) =>
                    p.category === selectedProduct.category &&
                    p.id !== selectedProduct.id
            )
            .slice(0, 3);
    }, [selectedProduct, orderedProducts]);

    const jsonLd = useMemo(() => {
        const specs = (businessHours || [])
            .filter((h) => !h.is_closed)
            .map((h) => ({
                '@type': 'OpeningHoursSpecification',
                dayOfWeek: DAY_SCHEMA[h.day_of_week] || 'Monday',
                opens: String(h.open_time || '09:00').slice(0, 5),
                closes: String(h.close_time || '17:00').slice(0, 5),
            }));

        return JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'Restaurant',
            name: social.name || 'DeSego Cevichería',
            description:
                'Restaurante peruano en Huaycán, Ate especializado en pescados, mariscos, ceviches, arroces marinos, frituras y rondas marinas.',
            servesCuisine: ['Peruana', 'Marina', 'Cevichería'],
            currenciesAccepted: 'PEN',
            telephone: social.whatsapp_phone || '+51 922 119 238',
            address: {
                '@type': 'PostalAddress',
                streetAddress:
                    social.address || 'UCV 19, Lote 13, Zona A, Huaycán, Ate',
                addressLocality: social.district || 'Ate',
                addressRegion: 'Lima',
                postalCode: social.postal_code || '15479',
                addressCountry: 'PE',
            },
            geo: {
                '@type': 'GeoCoordinates',
                latitude: social.latitude ?? -12.0125,
                longitude: social.longitude ?? -76.8285,
            },
            sameAs: [
                social.facebook || 'https://facebook.com/desegocevicheria',
                social.instagram || 'https://instagram.com/desegocevicheria',
                social.tiktok || 'https://www.tiktok.com/@desegocevicheria/',
            ].filter(Boolean),
            openingHoursSpecification: specs,
        });
    }, [social, businessHours]);

    const isStaff = Boolean(auth?.user?.role_code && auth.user.role_code !== 'customer');
    const hasActiveFilter = category !== 'all' || onlyFavorites || query.trim() !== '';

    return (
        <div
            ref={rootRef}
            className={`dg-site im-store ${vivid ? 'im-vivid' : ''} ${scenePaused ? 'im-paused' : ''}`}
        >
            <Head title="DeSego Cevichería · Pescados, mariscos y cocina marina en Huaycán, Ate">
                <link rel="icon" type="image/png" sizes="48x48" href="/images/favicon_desego.png" />
                <link rel="icon" type="image/png" href="/favicon.png" />
                <link rel="shortcut icon" href="/favicon.ico" />
                <link rel="apple-touch-icon" href="/images/favicon_desego.png" />
                <meta
                    name="description"
                    content="DeSego Cevichería en Huaycán, Ate: ceviches frescos, arroces marinos, chicharrones, jaleas, dúos, tríos y rondas marinas. Pedidos por delivery, recojo en local y reservas de mesa."
                />
                <meta name="robots" content="index, follow" />
                <meta name="theme-color" content="#004797" />
                <meta property="og:type" content="restaurant" />
                <meta
                    property="og:title"
                    content="DeSego Cevichería · Pescados, mariscos y cocina marina"
                />
                <meta
                    property="og:description"
                    content="Cevichería peruana en UCV 19, Lote 13, Zona A, Huaycán, Ate. Explora nuestra carta digital, haz tu pedido en línea o reserva tu mesa."
                />
                <meta property="og:locale" content="es_PE" />
                <meta property="og:site_name" content="DeSego Cevichería" />
                <meta
                    property="og:image"
                    content="/assets/images/hero_ceviche_plate.png"
                />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    property="twitter:title"
                    content="DeSego Cevichería · Cocina Marina en Huaycán"
                />
                <meta
                    name="twitter:description"
                    content="Pescados, mariscos y gastronomía marina peruana preparada al momento."
                />
                <script
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: jsonLd }}
                />
            </Head>

            <a
                href="#carta"
                className="im-skip"
                onClick={(e) => scrollToSection('carta', e)}
            >
                Ir a la carta
            </a>

            <header className="im-header">
                <a
                    href="#inicio"
                    onClick={(e) => scrollToSection('inicio', e)}
                    className="im-brand"
                    aria-label="DeSego Cevichería, inicio"
                >
                    <img
                        src="/assets/desego-logo.png"
                        alt="DESEGO CEVICHERÍA"
                        className="im-brand-logo"
                        width="800"
                        height="300"
                    />
                </a>

                <nav className="im-desktop-nav" aria-label="Accesos principales">
                    <a href="#carta" onClick={(e) => scrollToSection('carta', e)}>
                        La carta
                    </a>
                    <a href="#esencia" onClick={(e) => scrollToSection('esencia', e)}>
                        Nuestra esencia
                    </a>
                    {services.reservations_enabled !== false && (
                        <a href="#reservas" onClick={(e) => scrollToSection('reservas', e)}>
                            Reservas
                        </a>
                    )}
                    <a href="#contacto" onClick={(e) => scrollToSection('contacto', e)}>
                        Ubicación y horarios
                    </a>
                </nav>

                <div className="im-header-right">
                    <span
                        className={`im-status-badge ${isOpenNow ? 'is-open' : 'is-closed'}`}
                        title={social.schedule || 'Lun - Dom: 9:00 a. m. – 5:00 p. m.'}
                    >
                        <span className="im-status-dot" />
                        {isOpenNow ? 'Abierto ahora' : '9:00 a. m. – 5:00 p. m.'}
                    </span>

                    {isStaff && (
                        <Link href="/admin" className="im-pill im-pill-outline">
                            <ShieldCheck size={15} />
                            <span>Admin</span>
                        </Link>
                    )}

                    <Link
                        href={auth?.user ? '/cuenta' : '/ingresar'}
                        className="im-pill"
                        aria-label={auth?.user ? 'Ir a Mi cuenta' : 'Ingresar a mi cuenta'}
                    >
                        <UserRound size={15} />
                        <span>{auth?.user ? 'Mi cuenta' : 'Ingresar'}</span>
                    </Link>

                    {services.reservations_enabled !== false && (
                        <a
                            href="#reservas"
                            onClick={(e) => scrollToSection('reservas', e)}
                            className="im-pill im-pill-primary"
                        >
                            <span>Reservar mesa</span>
                        </a>
                    )}

                    <button
                        type="button"
                        className={`im-pill im-bag ${cart.count > 0 ? 'has-items' : ''}`}
                        onClick={() => setCartOpen(true)}
                        aria-label={`Abrir pedido, ${cart.count} productos`}
                    >
                        <ShoppingBag size={16} />
                        <span>{cart.count}</span>
                    </button>

                    <button
                        ref={menuButtonRef}
                        type="button"
                        className="im-pill im-mobile-toggle"
                        onClick={() => setNavigationOpen(!navigationOpen)}
                        aria-expanded={navigationOpen}
                        aria-controls="im-navigation"
                    >
                        {navigationOpen ? <X size={16} /> : <Menu size={16} />}
                        <span>{navigationOpen ? 'Cerrar' : 'Menú'}</span>
                    </button>
                </div>
            </header>

            {navigationOpen && (
                <nav
                    ref={navigationRef}
                    className="im-navigation"
                    id="im-navigation"
                    aria-label="Navegación principal"
                >
                    {[
                        ['inicio', 'Inicio'],
                        ['carta', 'Carta digital'],
                        ['esencia', 'Nuestra esencia'],
                        ...(services.reservations_enabled !== false
                            ? [['reservas', 'Reservar mesa']]
                            : []),
                        ['contacto', 'Ubicación y horarios'],
                    ].map(([id, label], index) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            onClick={(e) => scrollToSection(id, e)}
                        >
                            <small>0{index + 1}</small>
                            <span>{label}</span>
                            <ArrowUpRight size={26} />
                        </a>
                    ))}
                    <Link
                        href={auth?.user ? '/cuenta' : '/ingresar'}
                        onClick={closeNavigation}
                    >
                        <UserRound size={20} />
                        <span>{auth?.user ? 'Mi cuenta' : 'Ingresar / Registrarse'}</span>
                        <ArrowUpRight size={26} />
                    </Link>
                    {isStaff && (
                        <Link href="/admin" onClick={closeNavigation}>
                            <ShieldCheck size={20} />
                            <span>Panel Administrativo</span>
                            <ArrowUpRight size={26} />
                        </Link>
                    )}
                </nav>
            )}

            <main>
                {/* MÓDULO 1: HERO EDITORIAL ORDENADO Y ARMÓNICO */}
                <section className="im-intro" id="inicio">
                    <div className="im-hero-main">
                        <div className="im-hero-kicker">
                            <span className="im-kicker-dot" />
                            <span>DESEGO CEVICHERÍA · HUAYCÁN, ATE</span>
                        </div>
                        <h1>
                            El Pacífico.
                            <br />
                            En todos los <span>sentidos.</span>
                        </h1>
                        <div className="im-hero-actions">
                            <a
                                href="#carta"
                                onClick={(e) => scrollToSection('carta', e)}
                                className="im-btn-brand"
                            >
                                <span>Ver carta</span>
                                <ArrowUpRight size={17} />
                            </a>
                            <button
                                type="button"
                                onClick={(e) => {
                                    if (cart.count > 0) {
                                        setCartOpen(true);
                                    } else {
                                        scrollToSection('carta', e);
                                    }
                                }}
                                className="im-btn-outline-brand"
                            >
                                <ShoppingBag size={16} />
                                <span>Ordenar ahora</span>
                            </button>
                            {services.reservations_enabled !== false && (
                                <a
                                    href="#reservas"
                                    onClick={(e) => scrollToSection('reservas', e)}
                                    className="im-btn-tonal"
                                >
                                    <span>Reservar mesa</span>
                                    <ArrowRight size={16} />
                                </a>
                            )}
                        </div>
                    </div>

                    <aside className="im-hero-aside" aria-label="Resumen del restaurante">
                        <div className="im-hero-card">
                            <div className="im-hero-card-head">
                                <span
                                    className={`im-hero-live-pill ${isOpenNow ? 'is-open' : 'is-closed'}`}
                                >
                                    <span className="im-status-dot" />
                                    {isOpenNow ? 'Atendiendo ahora' : 'Cocina marina tradicional'}
                                </span>
                                <span className="im-hero-card-tag">PEN · S/</span>
                            </div>
                            <p>
                                Pescados, mariscos, ceviches frescos, arroces marinos, frituras y rondas preparadas al momento con el sabor auténtico de nuestra costa.
                            </p>
                            <div className="im-hero-meta-list">
                                <div>
                                    <Clock3 size={15} />
                                    <span>Lun a Dom: 9:00 a. m. – 5:00 p. m.</span>
                                </div>
                                <div>
                                    <MapPin size={15} />
                                    <span>
                                        {social.address ||
                                            'UCV 19, Lote 13, Zona A, Huaycán, Ate'}
                                    </span>
                                </div>
                                <div>
                                    <Truck size={15} />
                                    <span>Delivery a domicilio, recojo en local y salón</span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </section>

                <section
                    className="im-experience"
                    ref={stageRef}
                    onPointerMove={moveScene}
                    onPointerLeave={() => {
                        stageRef.current.style.setProperty('--pointer-x', '0deg');
                        stageRef.current.style.setProperty('--pointer-y', '0deg');
                    }}
                    aria-label="Experiencia interactiva de cocina marina"
                >
                    <OceanScene vivid={vivid} paused={scenePaused} />
                    <div className="im-scene-top">
                        <span>01 / EL ORIGEN DEL ANTOJO</span>
                        <div className="im-scene-controls">
                            <button
                                type="button"
                                onClick={() => {
                                    setScenePaused(!scenePaused);
                                    stageRef.current.style.setProperty('--pointer-x', '0deg');
                                    stageRef.current.style.setProperty('--pointer-y', '0deg');
                                }}
                                aria-pressed={scenePaused}
                            >
                                {scenePaused ? 'Reanudar escena' : 'Pausar escena'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setVivid(!vivid)}
                                aria-pressed={vivid}
                            >
                                <span className="im-palette-dot" /> Cambiar ambiente
                            </button>
                        </div>
                    </div>
                    <span className="im-scene-word" aria-hidden="true">
                        PURO
                        <br />
                        MAR.
                    </span>
                    <div className="im-floating-plate">
                        <div className="im-plate-inner">
                            <img
                                src="/assets/images/hero_ceviche_plate.png"
                                alt="Ceviche peruano con limón, cebolla morada, choclo y camote"
                                fetchPriority="high"
                            />
                        </div>
                        <span className="im-plate-ring" />
                    </div>
                    <span className="im-scene-caption">
                        FRESCURA QUE
                        <br />
                        SE SIENTE.
                    </span>
                    <a
                        href="#carta"
                        onClick={(e) => scrollToSection('carta', e)}
                        className="im-scene-cta"
                    >
                        <span>
                            Descubre
                            <br />
                            la carta.
                        </span>
                        <span className="im-circle-arrow">
                            <ArrowUpRight size={26} />
                        </span>
                    </a>
                    <div className="im-scene-bottom">
                        <span>EL SABOR NO SE EXPLICA. SE PRUEBA.</span>
                        <span>DESEGO CEVICHERÍA · HUAYCÁN, ATE</span>
                    </div>
                </section>

                {/* CATEGORÍAS DESTACADAS & PLATOS POPULARES */}
                <section className="im-highlights" aria-label="Especialidades y destacados">
                    {promotions.length > 0 && (
                        <div className="im-promo-banner">
                            <div>
                                <span className="im-promo-kicker">
                                    <Sparkles size={13} /> Promoción vigente
                                </span>
                                <h3>{promotions[0].name}</h3>
                                <p>
                                    {promotions[0].description ||
                                        `Beneficio especial aplicable desde ${money(promotions[0].min_subtotal)} de consumo.`}
                                </p>
                            </div>
                            <a
                                href="#carta"
                                onClick={(e) => scrollToSection('carta', e)}
                                className="im-promo-cta"
                            >
                                Aprovechar ahora <ArrowRight size={15} />
                            </a>
                        </div>
                    )}

                    <div className="im-highlights-block">
                        <div className="im-highlights-head">
                            <div>
                                <span className="im-eyebrow">EXPLORA POR ESPECIALIDAD</span>
                                <h3>Nuestras líneas de cocina marina</h3>
                            </div>
                        </div>
                        <div className="im-specialty-grid">
                            {(catalogCategories.length
                                ? catalogCategories
                                : categories.map((n) => ({ name: n, description: '' }))
                            ).map((cat) => {
                                const count = categoryCounts[cat.name] || 0;
                                const isActive = !onlyFavorites && category === cat.name;
                                return (
                                    <button
                                        key={cat.name}
                                        type="button"
                                        className={`im-specialty-card ${isActive ? 'is-active' : ''}`}
                                        onClick={(e) => {
                                            setCategory(cat.name);
                                            setOnlyFavorites(false);
                                            scrollToSection('carta', e);
                                        }}
                                    >
                                        <div className="im-specialty-top">
                                            <span className="im-specialty-count">
                                                {count > 0
                                                    ? `${count} ${count === 1 ? 'plato' : 'platos'}`
                                                    : 'Próximamente'}
                                            </span>
                                            <span className="im-specialty-arrow">
                                                <ArrowUpRight size={15} />
                                            </span>
                                        </div>
                                        <strong>{cat.name}</strong>
                                        {cat.description && <p>{cat.description}</p>}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {popularShowcase.length > 0 && (
                        <div className="im-highlights-block">
                            <div className="im-highlights-head">
                                <div>
                                    <span className="im-eyebrow">RECOMENDADOS DEL DÍA</span>
                                    <h3>Los favoritos de nuestra barra marina</h3>
                                </div>
                                <a
                                    href="#carta"
                                    onClick={(e) => scrollToSection('carta', e)}
                                    className="im-link-brand"
                                >
                                    Ver los {products.length} platos <ArrowRight size={14} />
                                </a>
                            </div>
                            <div className="im-popular-grid">
                                {popularShowcase.map((dish) => (
                                    <div key={dish.id} className="im-popular-card">
                                        <button
                                            type="button"
                                            className="im-popular-thumb"
                                            onClick={() => setSelectedProduct(dish)}
                                            aria-label={`Ver ${dish.name}`}
                                        >
                                            <img
                                                src={foodImage(dish)}
                                                alt={dish.name}
                                                loading="lazy"
                                            />
                                        </button>
                                        <div className="im-popular-body">
                                            {dish.code && (
                                                <span className="im-popular-code">
                                                    {dish.code}
                                                </span>
                                            )}
                                            <button
                                                type="button"
                                                className="im-popular-title"
                                                onClick={() => setSelectedProduct(dish)}
                                            >
                                                {dish.name}
                                            </button>
                                            <strong className="im-popular-price">
                                                {money(dish.price)}
                                            </strong>
                                        </div>
                                        <button
                                            type="button"
                                            className="im-popular-add"
                                            disabled={dish.can_order === false}
                                            onClick={() => addProduct(dish)}
                                            aria-label={`Agregar ${dish.name}`}
                                        >
                                            <Plus size={16} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </section>

                <div className="im-scroll-line">
                    <Plus size={15} />
                    <span>DESLIZA PARA DESCUBRIR</span>
                    <Plus size={15} />
                </div>

                <section className="im-statement" id="esencia" data-reveal>
                    <span className="im-eyebrow">EL MAR ES EL PUNTO DE PARTIDA.</span>
                    <div>
                        <h2>
                            Lo demás es
                            <br />
                            <span>puro instinto.</span>
                        </h2>
                        <p>
                            En DeSego Cevichería preparamos cada plato al momento con el auténtico carácter de la cocina marina peruana en Huaycán, Ate. Ceviches frescos, arroces al wok, frituras doradas y rondas pensadas para compartir en familia o entre amigos.
                        </p>
                        <a
                            href="#carta"
                            onClick={(e) => scrollToSection('carta', e)}
                            className="im-btn-brand"
                        >
                            <span>Explorar carta digital</span>
                            <ArrowUpRight size={16} />
                        </a>
                    </div>
                    <Waves size={56} strokeWidth={0.9} />
                </section>

                {/* MÓDULO 2: CARTA DIGITAL */}
                <section
                    className="im-menu-section"
                    id="carta"
                    aria-labelledby="menu-title"
                >
                    <div className="im-section-heading" data-reveal>
                        <div>
                            <span className="im-eyebrow">02 / CARTA DIGITAL OFICIAL</span>
                            <h2 id="menu-title">
                                La carta<span>.</span>
                            </h2>
                            <p>El sabor de nuestra costa peruana, preparado al instante.</p>
                        </div>
                        <div className="im-service-choice">
                            <span>Modalidad de atención</span>
                            <div className="im-order-mode" aria-label="Tipo de pedido">
                                {services.delivery_enabled !== false && (
                                    <button
                                        type="button"
                                        aria-pressed={mode === 'Delivery'}
                                        onClick={() => setMode('Delivery')}
                                    >
                                        A domicilio
                                    </button>
                                )}
                                {services.pickup_enabled !== false && (
                                    <button
                                        type="button"
                                        aria-pressed={mode === 'Para llevar'}
                                        onClick={() => setMode('Para llevar')}
                                    >
                                        Para recoger
                                    </button>
                                )}
                                {services.dine_in_enabled !== false && (
                                    <button
                                        type="button"
                                        aria-pressed={mode === 'Consumo en local'}
                                        onClick={() => setMode('Consumo en local')}
                                    >
                                        En el local
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="im-menu-tools">
                        <div className="im-categories" aria-label="Categorías de la carta">
                            {['all', ...categories].map((value) => (
                                <button
                                    key={value}
                                    type="button"
                                    aria-pressed={!onlyFavorites && category === value}
                                    onClick={() => {
                                        setOnlyFavorites(false);
                                        setCategory(value);
                                    }}
                                >
                                    {value === 'all' ? 'Toda la carta' : value}
                                </button>
                            ))}
                            <button
                                type="button"
                                aria-pressed={onlyFavorites}
                                onClick={() => setOnlyFavorites(!onlyFavorites)}
                                className="im-fav-filter-btn"
                            >
                                <Heart
                                    size={14}
                                    fill={onlyFavorites ? 'currentColor' : 'none'}
                                />
                                <span>Favoritos ({favorites.length})</span>
                            </button>
                        </div>

                        <div className="im-menu-controls-right">
                            <label className="im-search">
                                <Search size={17} />
                                <input
                                    type="search"
                                    aria-label="Buscar en la carta"
                                    placeholder="Buscar plato o código (ej. PF001)"
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                />
                            </label>
                            <select
                                aria-label="Ordenar carta"
                                className="im-sort-select"
                                value={sortBy}
                                onChange={(e) => setSortBy(e.target.value)}
                            >
                                <option value="default">Orden oficial</option>
                                <option value="featured">Destacados primero</option>
                                <option value="price_asc">Menor precio</option>
                                <option value="price_desc">Mayor precio</option>
                                <option value="rating">Mejor valorados</option>
                            </select>
                        </div>
                    </div>

                    <div className="im-menu-results" role="status">
                        <span>
                            Mostrando <strong>{filteredProducts.length}</strong>{' '}
                            {filteredProducts.length === 1
                                ? 'plato disponible'
                                : 'platos disponibles'}
                            {category !== 'all' && !onlyFavorites ? ` en ${category}` : ''}
                        </span>
                        {hasActiveFilter && (
                            <button
                                type="button"
                                className="im-reset-filters"
                                onClick={() => {
                                    setCategory('all');
                                    setOnlyFavorites(false);
                                    setQuery('');
                                }}
                            >
                                Limpiar filtros ×
                            </button>
                        )}
                    </div>

                    <div className="im-menu-grid">
                        {filteredProducts.map((product) => {
                            const isFav = favorites.includes(Number(product.id));
                            return (
                                <article className="im-product" key={product.id}>
                                    <div className="im-product-media">
                                        <button
                                            type="button"
                                            className="im-product-visual"
                                            onClick={() => setSelectedProduct(product)}
                                            aria-label={`Ver detalles de ${product.name}`}
                                        >
                                            <img
                                                src={foodImage(product)}
                                                alt={product.name}
                                                loading="lazy"
                                            />
                                            {product.code && (
                                                <span className="im-product-code-tag">
                                                    {product.code}
                                                </span>
                                            )}
                                            <span
                                                className="im-product-explore"
                                                aria-hidden="true"
                                            >
                                                <ArrowUpRight size={18} />
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            className={`im-product-fav ${isFav ? 'is-fav' : ''}`}
                                            onClick={(ev) => toggleFavorite(product, ev)}
                                            aria-label={
                                                isFav
                                                    ? `Quitar ${product.name} de favoritos`
                                                    : `Guardar ${product.name} en favoritos`
                                            }
                                            aria-pressed={isFav}
                                        >
                                            <Heart
                                                size={16}
                                                fill={isFav ? 'currentColor' : 'none'}
                                            />
                                        </button>
                                    </div>

                                    <div className="im-product-info">
                                        <div className="im-product-meta">
                                            <span className="im-product-cat">
                                                {product.category}
                                            </span>
                                            <div className="im-product-badges">
                                                {product.is_new && (
                                                    <span className="im-badge im-badge-new">
                                                        Nuevo
                                                    </span>
                                                )}
                                                <span
                                                    className={`im-badge ${product.can_order === false ? 'im-badge-out' : 'im-badge-ok'}`}
                                                >
                                                    {product.can_order === false
                                                        ? 'Agotado'
                                                        : 'Disponible'}
                                                </span>
                                                {product.review?.count > 0 && (
                                                    <span className="im-badge-rating">
                                                        ★ {Number(product.review.average).toFixed(1)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <h3>
                                            <button
                                                type="button"
                                                onClick={() => setSelectedProduct(product)}
                                            >
                                                {product.name}
                                            </button>
                                        </h3>

                                        {product.included_items && (
                                            <p className="im-combo-preview">
                                                <strong>Incluye:</strong> {product.included_items}
                                            </p>
                                        )}

                                        <p className="im-product-desc">
                                            {product.description ||
                                                'Preparado al momento con el sabor tradicional de DeSego.'}
                                        </p>

                                        <div className="im-product-footer">
                                            <div className="im-product-price-wrap">
                                                <small>Precio</small>
                                                <strong>{money(product.price)}</strong>
                                            </div>
                                            <div className="im-product-actions">
                                                <button
                                                    type="button"
                                                    className="im-btn-details"
                                                    onClick={() => setSelectedProduct(product)}
                                                >
                                                    Detalles
                                                </button>
                                                <button
                                                    type="button"
                                                    className="im-add"
                                                    disabled={product.can_order === false}
                                                    onClick={() => addProduct(product)}
                                                    aria-label={
                                                        product.can_order === false
                                                            ? `${product.name}, no disponible`
                                                            : `Agregar ${product.name}`
                                                    }
                                                >
                                                    {product.can_order === false ? (
                                                        <span>Agotado</span>
                                                    ) : (
                                                        <>
                                                            <span>Agregar</span>
                                                            <Plus size={16} />
                                                        </>
                                                    )}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>

                    {!filteredProducts.length && (
                        <div className="im-empty">
                            <Fish size={36} />
                            <p>
                                {category === 'Bebidas' && !query && !onlyFavorites
                                    ? 'La carta de bebidas se habilitará próximamente con nuestros precios oficiales.'
                                    : products.length
                                      ? 'No encontramos platos con ese criterio. Prueba otra categoría o término de búsqueda.'
                                      : 'Nuestra carta estará disponible pronto.'}
                            </p>
                            {products.length > 0 && (
                                <button
                                    type="button"
                                    className="im-btn-brand"
                                    onClick={() => {
                                        setQuery('');
                                        setCategory('all');
                                        setOnlyFavorites(false);
                                    }}
                                >
                                    <span>Ver toda la carta</span>
                                    <ArrowRight size={15} />
                                </button>
                            )}
                        </div>
                    )}

                    <div className="im-menu-footnote">
                        <span>
                            ¿Deseas poco ají, sin cebolla o alguna indicación especial? Puedes anotarlo en cada plato o al confirmar tu pedido.
                        </span>
                        <button type="button" onClick={() => setCartOpen(true)}>
                            <span>Ver mi pedido ({cart.count})</span>
                            <ArrowUpRight size={16} />
                        </button>
                    </div>
                </section>

                <section className="im-interlude" data-reveal>
                    <img
                        src="/assets/images/ambience_chef_plating.jpg"
                        alt="El cuidado de nuestra cocina al preparar cada plato"
                        loading="lazy"
                    />
                    <div>
                        <span className="im-eyebrow">
                            MANOS QUE SABEN. SABORES QUE SE QUEDAN.
                        </span>
                        <h2>
                            Sin prisa.
                            <br />
                            Con todo
                            <br />
                            <span>el sabor.</span>
                        </h2>
                        {services.reservations_enabled !== false && (
                            <a
                                className="im-btn-brand"
                                href="#reservas"
                                onClick={(e) => scrollToSection('reservas', e)}
                            >
                                <span>Reservar una mesa</span>
                                <ArrowUpRight size={17} />
                            </a>
                        )}
                    </div>
                    <span className="im-interlude-number">DESEGO / 03</span>
                </section>

                {/* MÓDULO 7: RESERVAS */}
                {services.reservations_enabled !== false && (
                    <section className="im-reservation" id="reservas">
                        <div className="im-reservation-intro" data-reveal>
                            <span className="im-eyebrow">
                                04 / RESERVA DE MESAS EN SALÓN
                            </span>
                            <h2>
                                Hagamos
                                <br />
                                <span>un plan.</span>
                            </h2>
                            <p>
                                Para celebrar, reencontrarse en familia o disfrutar nuestra carta marina recién salida de cocina. Contamos con 12 mesas en salón listas para recibirte.
                            </p>
                            <div className="im-reservation-facts">
                                <div className="im-fact-row">
                                    <MapPin size={17} />
                                    <span>
                                        {social.address ||
                                            branch?.address ||
                                            'UCV 19, Lote 13, Zona A, Huaycán, Ate'}
                                    </span>
                                </div>
                                <div className="im-fact-row">
                                    <Clock3 size={17} />
                                    <span>
                                        {social.schedule ||
                                            'Lunes a Domingo: 9:00 a. m. – 5:00 p. m.'}
                                    </span>
                                </div>
                            </div>
                            <a
                                className="im-contact-wa"
                                href={social.whatsapp_url || 'https://wa.me/51922119238'}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <BrandWhatsapp size={17} />
                                <span>
                                    Consultas por WhatsApp: {social.whatsapp_phone || '+51 922 119 238'}
                                </span>
                                <ArrowUpRight size={15} />
                            </a>
                            <img
                                src="/assets/images/ambience_dining_room.jpg"
                                alt="Salón principal de DeSego Cevichería"
                                loading="lazy"
                            />
                        </div>
                        <ReservationForm />
                    </section>
                )}

                {/* MÓDULO 1 & 6: SECCIÓN DE UBICACIÓN, HORARIOS Y CONTACTO OFICIAL */}
                <section className="im-location-section" id="contacto">
                    <div className="im-location-head">
                        <span className="im-eyebrow">05 / VISÍTANOS EN HUAYCÁN, ATE</span>
                        <h2>Ubicación, horarios y atención directa<span>.</span></h2>
                        <p>
                            Encuéntranos en el corazón de la Zona A de Huaycán o comunícate por nuestros canales oficiales.
                        </p>
                    </div>

                    <div className="im-location-grid">
                        <div className="im-location-card">
                            <div className="im-location-venue">
                                <span className="im-venue-badge">Sede Oficial</span>
                                <h3>{social.name || 'DeSego Cevichería'}</h3>
                                <p>
                                    <MapPin size={16} />
                                    <span>
                                        {social.address ||
                                            'UCV 19, Lote 13, Zona A, Huaycán, Ate'}{' '}
                                        · Lima, Perú (CP {social.postal_code || '15479'})
                                    </span>
                                </p>
                            </div>

                            <div className="im-hours-box">
                                <h4>
                                    <Clock3 size={16} />
                                    <span>Horarios de atención semanal</span>
                                </h4>
                                <div className="im-hours-list">
                                    {(businessHours.length
                                        ? businessHours
                                        : [
                                              { day_of_week: 1, day_name: 'Lunes', open_time: '09:00', close_time: '17:00', is_closed: false },
                                              { day_of_week: 2, day_name: 'Martes', open_time: '09:00', close_time: '17:00', is_closed: false },
                                              { day_of_week: 3, day_name: 'Miércoles', open_time: '09:00', close_time: '17:00', is_closed: false },
                                              { day_of_week: 4, day_name: 'Jueves', open_time: '09:00', close_time: '17:00', is_closed: false },
                                              { day_of_week: 5, day_name: 'Viernes', open_time: '09:00', close_time: '17:00', is_closed: false },
                                              { day_of_week: 6, day_name: 'Sábado', open_time: '09:00', close_time: '17:00', is_closed: false },
                                              { day_of_week: 7, day_name: 'Domingo', open_time: '09:00', close_time: '17:00', is_closed: false },
                                          ]
                                    ).map((h) => {
                                        const isToday = Number(h.day_of_week) === todayIso;
                                        return (
                                            <div
                                                key={h.day_of_week}
                                                className={`im-hour-row ${isToday ? 'is-today' : ''}`}
                                            >
                                                <span>
                                                    {h.day_name}
                                                    {isToday && <em>Hoy</em>}
                                                </span>
                                                <strong className={h.is_closed ? 'is-closed' : ''}>
                                                    {h.is_closed
                                                        ? 'Cerrado'
                                                        : `${String(h.open_time).slice(0, 5)} – ${String(h.close_time).slice(0, 5)}`}
                                                </strong>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="im-social-strip">
                                <a
                                    href={social.whatsapp_url || 'https://wa.me/51922119238'}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="im-social-wa"
                                >
                                    <BrandWhatsapp size={17} />
                                    <span>WhatsApp {social.whatsapp_phone || '+51 922 119 238'}</span>
                                </a>
                                <div className="im-social-links">
                                    {social.facebook && (
                                        <a
                                            href={social.facebook}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Facebook <ArrowUpRight size={13} />
                                        </a>
                                    )}
                                    {social.instagram && (
                                        <a
                                            href={social.instagram}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Instagram <ArrowUpRight size={13} />
                                        </a>
                                    )}
                                    {social.tiktok && (
                                        <a
                                            href={social.tiktok}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            TikTok <ArrowUpRight size={13} />
                                        </a>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="im-location-map-wrap">
                            <InteractiveMap
                                mode="restaurant"
                                height={430}
                                restaurantLat={social.latitude ?? -12.0125}
                                restaurantLng={social.longitude ?? -76.8285}
                                restaurantName={social.name || 'DeSego Cevichería'}
                                restaurantAddress={
                                    social.address || 'UCV 19, Lote 13, Zona A, Huaycán, Ate'
                                }
                            />
                        </div>
                    </div>
                </section>
            </main>

            <footer className="im-footer">
                <div className="im-footer-top">
                    <div>
                        <span className="im-eyebrow">EL MAR NOS VUELVE A ENCONTRAR.</span>
                        <a
                            href="#carta"
                            onClick={(e) => scrollToSection('carta', e)}
                            className="im-footer-cta"
                        >
                            <span>¿Repetimos?</span>
                            <ArrowUpRight />
                        </a>
                    </div>
                    <div className="im-footer-quick-actions">
                        <a
                            href="#carta"
                            onClick={(e) => scrollToSection('carta', e)}
                            className="im-btn-brand"
                        >
                            <span>Ver carta digital</span>
                            <ArrowUpRight size={16} />
                        </a>
                        <a
                            href={social.whatsapp_url || 'https://wa.me/51922119238'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="im-footer-wa-pill"
                        >
                            <BrandWhatsapp size={16} />
                            <span>{social.whatsapp_phone || '+51 922 119 238'}</span>
                        </a>
                    </div>
                </div>

                <div className="im-footer-meta-bar">
                    <div className="im-footer-address">
                        <strong>{social.name || 'DeSego Cevichería'}</strong>
                        <span>
                            {social.address || 'UCV 19, Lote 13, Zona A, Huaycán, Ate'} (CP{' '}
                            {social.postal_code || '15479'}) ·{' '}
                            {social.schedule || 'Lunes a Domingo: 9:00 a. m. – 5:00 p. m.'}
                        </span>
                    </div>
                    <div className="im-footer-socials">
                        <a
                            href={social.facebook || 'https://facebook.com/desegocevicheria'}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Facebook
                        </a>
                        <a
                            href={social.instagram || 'https://instagram.com/desegocevicheria'}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Instagram
                        </a>
                        <a
                            href={social.tiktok || 'https://www.tiktok.com/@desegocevicheria/'}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            TikTok
                        </a>
                    </div>
                </div>

                <span className="im-footer-word" aria-hidden="true">
                    DESEGO®
                </span>

                <div className="im-footer-bottom">
                    <span>
                        © {new Date().getFullYear()} DESEGO CEVICHERÍA · COCINA MARINA PERUANA · HUAYCÁN, ATE, LIMA
                    </span>
                    <div className="im-footer-nav-links">
                        <Link href={auth?.user ? '/cuenta' : '/ingresar'}>
                            Mi cuenta <ArrowUpRight size={13} />
                        </Link>
                        <a href="#carta" onClick={(e) => scrollToSection('carta', e)}>
                            La carta <ArrowUpRight size={13} />
                        </a>
                        <a href="#contacto" onClick={(e) => scrollToSection('contacto', e)}>
                            Ubicación <ArrowUpRight size={13} />
                        </a>
                        <a href="#inicio" onClick={(e) => scrollToSection('inicio', e)}>
                            Volver arriba ↑
                        </a>
                    </div>
                </div>
            </footer>

            <Checkout
                open={cartOpen}
                close={() => setCartOpen(false)}
                cart={cart}
                mode={mode}
                setMode={setMode}
                fee={fee}
                accountUser={auth?.user}
                lastOrder={lastOrder}
                deliveryZones={catalog?.delivery_zones || []}
                social={social}
                branch={branch}
                customerProfile={customerProfile}
                customerAddresses={customerAddresses}
                paymentMethods={paymentMethods}
                services={services}
            />

            {/* MÓDULO 3: DETALLE COMPLETO DEL PRODUCTO */}
            <dialog
                ref={detailDialog}
                className="im-detail-dialog"
                onCancel={() => setSelectedProduct(null)}
                onClose={() => setSelectedProduct(null)}
                onClick={(event) => {
                    if (event.target === detailDialog.current) {
                        setSelectedProduct(null);
                    }
                }}
            >
                {selectedProduct && (
                    <div className="im-detail-layout">
                        <button
                            type="button"
                            className="im-dialog-close"
                            onClick={() => setSelectedProduct(null)}
                            aria-label="Cerrar detalle"
                        >
                            <X size={18} />
                        </button>

                        <div className="im-detail-media">
                            <div className="im-detail-main-photo">
                                <img
                                    src={activeGalleryImg || foodImage(selectedProduct)}
                                    alt={selectedProduct.name}
                                />
                                {selectedProduct.code && (
                                    <span className="im-product-code-tag">
                                        {selectedProduct.code}
                                    </span>
                                )}
                            </div>
                            {detailGallery.length > 1 && (
                                <div className="im-detail-thumbs">
                                    {detailGallery.map((gUrl, idx) => (
                                        <button
                                            key={idx}
                                            type="button"
                                            className={`im-detail-thumb ${activeGalleryImg === gUrl ? 'is-active' : ''}`}
                                            onClick={() => setActiveGalleryImg(gUrl)}
                                        >
                                            <img src={gUrl} alt="" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="im-detail-body">
                            <div className="im-detail-top-meta">
                                <span className="im-eyebrow">{selectedProduct.category}</span>
                                <span
                                    className={`im-badge ${selectedProduct.can_order === false ? 'im-badge-out' : 'im-badge-ok'}`}
                                >
                                    {selectedProduct.can_order === false
                                        ? 'No disponible'
                                        : 'Disponible hoy'}
                                </span>
                            </div>

                            <h2>{selectedProduct.name}</h2>

                            {selectedProduct.included_items && (
                                <div className="im-detail-combo-box">
                                    <strong>Combinación incluida:</strong>{' '}
                                    {selectedProduct.included_items}
                                </div>
                            )}

                            <p className="im-detail-desc">
                                {selectedProduct.description ||
                                    'Preparado al momento con ingredientes frescos seleccionados en nuestra cocina marina.'}
                            </p>

                            {selectedProduct.allergens && (
                                <p className="im-allergens">
                                    Alérgenos: {selectedProduct.allergens}
                                </p>
                            )}

                            {(selectedProduct.option_groups || []).length > 0 && (
                                <div className="im-detail-options">
                                    {selectedProduct.option_groups.map((grp) => (
                                        <div key={grp.id} className="im-option-group">
                                            <div className="im-option-group-head">
                                                <span>{grp.name}</span>
                                                <small>
                                                    {grp.is_required ? 'Obligatorio' : 'Opcional'}
                                                </small>
                                            </div>
                                            <div className="im-option-list">
                                                {(grp.options || []).map((opt) => {
                                                    const checked = detailOptions.includes(
                                                        Number(opt.id)
                                                    );
                                                    return (
                                                        <label
                                                            key={opt.id}
                                                            className={`im-option-item ${checked ? 'is-checked' : ''}`}
                                                        >
                                                            <span>
                                                                <input
                                                                    type={
                                                                        Number(
                                                                            grp.max_selections || 1
                                                                        ) === 1
                                                                            ? 'radio'
                                                                            : 'checkbox'
                                                                    }
                                                                    name={`grp-${grp.id}`}
                                                                    checked={checked}
                                                                    onChange={() =>
                                                                        toggleDetailOption(
                                                                            grp,
                                                                            opt.id
                                                                        )
                                                                    }
                                                                />
                                                                {opt.name}
                                                            </span>
                                                            <strong>
                                                                {Number(opt.price_delta) > 0
                                                                    ? `+ ${money(opt.price_delta)}`
                                                                    : 'Incluido'}
                                                            </strong>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div className="im-detail-customize">
                                <div className="im-detail-qty-row">
                                    <span>Cantidad</span>
                                    <div className="im-qty-stepper">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDetailQty((q) => Math.max(1, q - 1))
                                            }
                                            aria-label="Reducir cantidad"
                                        >
                                            <Minus size={14} />
                                        </button>
                                        <strong>{detailQty}</strong>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDetailQty((q) => Math.min(20, q + 1))
                                            }
                                            aria-label="Aumentar cantidad"
                                        >
                                            <Plus size={14} />
                                        </button>
                                    </div>
                                </div>

                                <label className="im-detail-note-label">
                                    <span>Observaciones para cocina (opcional)</span>
                                    <input
                                        type="text"
                                        maxLength={160}
                                        className="focus:outline-none focus:ring-0"
                                        placeholder="Ej. Sin culantro, poco ají, limón aparte..."
                                        value={detailNotes}
                                        onChange={(e) => setDetailNotes(e.target.value)}
                                    />
                                </label>
                            </div>

                            <div className="im-detail-cta-bar">
                                <div className="im-detail-total-box">
                                    <small>Subtotal</small>
                                    <strong>{money(detailUnitPrice * detailQty)}</strong>
                                </div>
                                <button
                                    type="button"
                                    className="im-btn-brand"
                                    disabled={selectedProduct.can_order === false}
                                    onClick={() => {
                                        addProduct(
                                            selectedProduct,
                                            detailQty,
                                            detailOptions,
                                            detailNotes
                                        );
                                        setSelectedProduct(null);
                                    }}
                                >
                                    <span>
                                        {selectedProduct.can_order === false
                                            ? 'No disponible'
                                            : 'Agregar a mi pedido'}
                                    </span>
                                    <Plus size={17} />
                                </button>
                            </div>

                            {/* RESEÑAS APROBADAS DEL PLATO */}
                            <div className="im-detail-section">
                                <h4>
                                    <Star size={14} filled />
                                    <span>Opiniones de comensales ({productReviews.length})</span>
                                </h4>
                                {loadingReviews ? (
                                    <p className="im-detail-muted">Cargando opiniones…</p>
                                ) : productReviews.length === 0 ? (
                                    <p className="im-detail-muted">
                                        Aún no hay reseñas publicadas para este plato.
                                    </p>
                                ) : (
                                    <div className="im-reviews-list">
                                        {productReviews.slice(0, 4).map((rv) => (
                                            <div key={rv.id} className="im-review-item">
                                                <div className="im-review-head">
                                                    <strong>{rv.reviewer_name}</strong>
                                                    <span>
                                                        {'★'.repeat(Number(rv.rating || 5))}
                                                    </span>
                                                </div>
                                                {rv.comment && <p>{rv.comment}</p>}
                                                {rv.photo_url && (
                                                    <img
                                                        src={rv.photo_url}
                                                        alt="Foto de reseña"
                                                        className="im-review-photo"
                                                    />
                                                )}
                                                {rv.admin_reply && (
                                                    <div className="im-review-reply">
                                                        <strong>Respuesta de DeSego:</strong>{' '}
                                                        {rv.admin_reply}
                                                    </div>
                                                )}
                                                <button
                                                    type="button"
                                                    className="im-review-helpful"
                                                    onClick={() => markReviewHelpful(rv.id)}
                                                >
                                                    <ThumbsUp size={12} /> Útil (
                                                    {rv.helpful_count || 0})
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* PLATOS RELACIONADOS */}
                            {relatedProducts.length > 0 && (
                                <div className="im-detail-section">
                                    <h4>También te puede provocar</h4>
                                    <div className="im-related-grid">
                                        {relatedProducts.map((rel) => (
                                            <button
                                                key={rel.id}
                                                type="button"
                                                className="im-related-card"
                                                onClick={() => setSelectedProduct(rel)}
                                            >
                                                <img src={foodImage(rel)} alt={rel.name} />
                                                <span>{rel.name}</span>
                                                <strong>{money(rel.price)}</strong>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </dialog>

            <div className={`im-toast ${toast ? 'visible' : ''}`} role="status">
                <Check size={17} />
                <span>{toast}</span>
                <button
                    type="button"
                    onClick={() => {
                        setToast('');
                        setCartOpen(true);
                    }}
                >
                    Ver pedido <ArrowRight size={15} />
                </button>
            </div>

            <ChatBot />
        </div>
    );
}
