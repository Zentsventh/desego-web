import React, { useEffect, useState } from 'react';

const SCRIPT_URL = 'https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js';
let loader = null;

/** Loads the <model-viewer> web component once, and only when a dish actually has a 3D model. */
function loadModelViewer() {
    if (customElements.get('model-viewer')) {
        return Promise.resolve();
    }
    loader ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.type = 'module';
        script.src = SCRIPT_URL;
        script.onload = resolve;
        script.onerror = () => {
            loader = null;
            reject(new Error('model-viewer'));
        };
        document.head.appendChild(script);
    });

    return loader;
}

/**
 * Rotatable 3D dish. Shows the photo while the model loads and keeps it if the model or the
 * script cannot be loaded, so the dish always has something to look at.
 */
export default function DishModel({ src, poster, name }) {
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let active = true;
        loadModelViewer().then(() => active && setReady(true)).catch(() => active && setFailed(true));

        return () => {
            active = false;
        };
    }, []);

    if (failed || !ready) {
        return <img src={poster} alt={name} />;
    }

    return (
        <model-viewer
            src={src}
            poster={poster}
            alt={`Modelo 3D de ${name}`}
            camera-controls=""
            auto-rotate=""
            shadow-intensity="1"
            interaction-prompt="auto"
            loading="lazy"
            onError={() => setFailed(true)}
        />
    );
}

