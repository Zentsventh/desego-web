import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowUpRight, ArrowRight, ShoppingBag, Plus, X, Search, Fish, Waves, MapPin, Check, UserRound } from '../../Components/Restaurant/Icons';
import { useCart } from '../../hooks/useCart';
import { Checkout, ErrorList, money } from '../../Components/Restaurant/ShopParts';
import ChatBot from '../../Components/Restaurant/ChatBot';
import '../../../css/restaurant.css';
import '../../../css/restaurant-mobile.css';
import '../../../css/restaurant-immersive.css';

const FOOD_IMAGES = { ceviche: '/assets/images/dish_ceviche_clasico.jpg', arroz: '/assets/images/dish_arroz_mariscos.jpg', pescado: '/assets/images/food_pescado.jpg', chicha: '/assets/images/drink_chicha_morada.jpg', chocolate: '/assets/images/food_chocolate.jpg', tiradito: '/assets/images/dish_tiradito.jpg' };
function foodImage(product) {
    const key = Object.keys(FOOD_IMAGES).find(word => product.name.toLowerCase().includes(word));
    return key ? FOOD_IMAGES[key] : product.image || FOOD_IMAGES.ceviche;
}

/** Escena de ingredientes renderizada en WebGL; el contenido permanece en HTML. */
function OceanScene({ vivid, paused }) {
    const canvasRef = useRef(null);
    const palette = useRef(vivid);
    const motionPaused = useRef(paused);
    const invalidate = useRef(() => {});
    useEffect(() => { palette.current = vivid; motionPaused.current = paused; invalidate.current(); }, [vivid, paused]);
    useEffect(() => {
        const canvas = canvasRef.current;
        const gl = canvas.getContext('webgl', { alpha: false, antialias: false, powerPreference: 'low-power' });
        if (!gl) return;
        const vertexSource = 'attribute vec2 a; void main(){gl_Position=vec4(a,0.,1.);}';
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
            return gl.getShaderParameter(shader, gl.COMPILE_STATUS) ? shader : null;
        }
        const vertex = compile(gl.VERTEX_SHADER, vertexSource);
        const fragment = compile(gl.FRAGMENT_SHADER, fragmentSource);
        if (!vertex || !fragment) { shaders.forEach(shader => gl.deleteShader(shader)); return; }
        const program = gl.createProgram();
        gl.attachShader(program, vertex); gl.attachShader(program, fragment); gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) { gl.deleteProgram(program); shaders.forEach(shader => gl.deleteShader(shader)); return; }
        gl.useProgram(program);
        const buffer = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1,1,-1,-1,1,-1,1,1,-1,1,1]), gl.STATIC_DRAW);
        const attribute = gl.getAttribLocation(program, 'a'); gl.enableVertexAttribArray(attribute); gl.vertexAttribPointer(attribute, 2, gl.FLOAT, false, 0, 0);
        const locations = Object.fromEntries(['resolution','pointer','time','palette'].map(name => [name, gl.getUniformLocation(program, name)]));
        const mouse = { x: 0, y: 0 }; const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let frame = 0, visible = true, disposed = false, sceneTime = 0, previousTime = 0;
        function resize() {
            const rect = canvas.getBoundingClientRect();
            const scale = Math.min(window.devicePixelRatio || 1, 1.25, 1100 / Math.max(rect.width, 1));
            canvas.width = Math.max(1, Math.round(rect.width * scale)); canvas.height = Math.max(1, Math.round(rect.height * scale));
            gl.viewport(0, 0, canvas.width, canvas.height);
            start();
        }
        function move(event) {
            const rect = canvas.getBoundingClientRect();
            mouse.x = (event.clientX - rect.left) / rect.width - .5;
            mouse.y = .5 - (event.clientY - rect.top) / rect.height;
        }
        function render(now) {
            frame = 0;
            if (disposed || !visible || document.hidden) return;
            if (!motionPaused.current && !reduced.matches) sceneTime += Math.min((now - previousTime) * .001, .05);
            previousTime = now;
            gl.uniform2f(locations.resolution, canvas.width, canvas.height);
            gl.uniform2f(locations.pointer, reduced.matches || motionPaused.current ? 0 : mouse.x, reduced.matches || motionPaused.current ? 0 : mouse.y);
            gl.uniform1f(locations.time, sceneTime);
            gl.uniform1f(locations.palette, palette.current ? 1 : 0);
            gl.drawArrays(gl.TRIANGLES, 0, 6);
            if (!motionPaused.current && !reduced.matches) frame = requestAnimationFrame(render);
        }
        function start() { if (!frame && !disposed && visible && !document.hidden) frame = requestAnimationFrame(render); }
        invalidate.current = start;
        const resizeObserver = new ResizeObserver(resize); resizeObserver.observe(canvas);
        const observer = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; if (visible) start(); else { cancelAnimationFrame(frame); frame = 0; } }); observer.observe(canvas);
        canvas.parentElement.addEventListener('pointermove', move);
        document.addEventListener('visibilitychange', start);
        resize(); start();
        return () => { disposed = true; invalidate.current = () => {}; cancelAnimationFrame(frame); resizeObserver.disconnect(); observer.disconnect(); canvas.parentElement?.removeEventListener('pointermove', move); document.removeEventListener('visibilitychange', start); gl.deleteBuffer(buffer); gl.deleteProgram(program); shaders.forEach(shader => gl.deleteShader(shader)); };
    }, []);
    return <canvas ref={canvasRef} className="im-canvas" aria-hidden="true"/>;
}

export default function Store({ catalog, lastOrder }) {
    const { auth, flash } = usePage().props;
    const { products = [], branch, delivery_fee: fee = 5 } = catalog || {};
    const cart = useCart(products);
    const [cartOpen, setCartOpen] = useState(false);
    const [navigationOpen, setNavigationOpen] = useState(false);
    const [mode, setMode] = useState('Delivery');
    const [category, setCategory] = useState('all');
    const [query, setQuery] = useState('');
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [vivid, setVivid] = useState(false);
    const [scenePaused, setScenePaused] = useState(false);
    const [toast, setToast] = useState('');
    const detailDialog = useRef(null);
    const toastTimer = useRef(null);
    const stageRef = useRef(null);
    const rootRef = useRef(null);
    const navigationRef = useRef(null);
    const menuButtonRef = useRef(null);
    const orderedProducts = useMemo(() => {
        const categoryRank = value => /plato|ceviche|fondo|entrada/i.test(value || '') ? 0 : /bebida/i.test(value || '') ? 1 : /postre/i.test(value || '') ? 2 : 3;
        return [...products].sort((first, second) => categoryRank(first.category) - categoryRank(second.category) || first.name.localeCompare(second.name, 'es'));
    }, [products]);
    const categories = useMemo(() => [...new Set(orderedProducts.map(product => product.category).filter(Boolean))], [orderedProducts]);
    const filteredProducts = orderedProducts.filter(product => (category === 'all' || product.category === category) && `${product.name} ${product.description || ''}`.toLocaleLowerCase('es').includes(query.trim().toLocaleLowerCase('es')));
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const reservation = useForm({ customer_name: auth?.user?.name || '', email: auth?.user?.email || '', phone: '', date: today, time: '13:30', people: 2, notes: '' });
    useEffect(() => () => clearTimeout(toastTimer.current), []);
    useEffect(() => {
        if (!navigationOpen) return;
        function dismissNavigation(event) {
            if (event.type === 'keydown' && event.key === 'Escape') {
                setNavigationOpen(false);
                menuButtonRef.current?.focus();
            }
            if (event.type === 'pointerdown' && !navigationRef.current?.contains(event.target) && !menuButtonRef.current?.contains(event.target)) setNavigationOpen(false);
        }
        document.addEventListener('keydown', dismissNavigation);
        document.addEventListener('pointerdown', dismissNavigation);
        return () => { document.removeEventListener('keydown', dismissNavigation); document.removeEventListener('pointerdown', dismissNavigation); };
    }, [navigationOpen]);
    useEffect(() => { if (selectedProduct && !detailDialog.current.open) detailDialog.current.showModal(); if (!selectedProduct && detailDialog.current.open) detailDialog.current.close(); }, [selectedProduct]);
    useEffect(() => {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), { threshold: .08 });
        rootRef.current.querySelectorAll('[data-reveal]').forEach(element => observer.observe(element));
        return () => observer.disconnect();
    }, []);
    function addProduct(product) {
        if (product.can_order === false) return;
        cart.add(product.id); setToast(`${product.name} se agregó a tu pedido.`);
        clearTimeout(toastTimer.current); toastTimer.current = setTimeout(() => setToast(''), 3200);
    }
    function moveScene(event) {
        if (scenePaused || event.pointerType !== 'mouse' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const rect = event.currentTarget.getBoundingClientRect();
        stageRef.current.style.setProperty('--pointer-x', `${((event.clientX - rect.left) / rect.width - .5) * 18}deg`);
        stageRef.current.style.setProperty('--pointer-y', `${((event.clientY - rect.top) / rect.height - .5) * -15}deg`);
    }
    function closeNavigation() { setNavigationOpen(false); }
    return <div ref={rootRef} className={`dg-site im-store ${vivid ? 'im-vivid' : ''} ${scenePaused ? 'im-paused' : ''}`}>
        <Head title="El Pacífico, en todos los sentidos"><meta name="description" content="Explora Desego: ceviche, cocina marina peruana y una experiencia que despierta los sentidos. Carta, pedidos y reservas."/><meta name="theme-color" content="#f0f1f5"/></Head>
        <a href="#carta" className="im-skip">Ir a la carta</a>
        <header className="im-header"><a href="#inicio" className="im-brand" aria-label="Desego, inicio">DESEGO<span>®</span></a><nav className="im-desktop-nav" aria-label="Accesos principales"><a href="#carta">La carta</a><a href="#esencia">Nuestra esencia</a><a href="#reservas">Reservas</a></nav><div className="im-header-right"><a href="#reservas" className="im-pill im-pill-dark">Una mesa para ti <span>●</span></a><button className="im-pill im-bag" onClick={() => setCartOpen(true)} aria-label={`Abrir pedido, ${cart.count} productos`}><ShoppingBag size={16}/><span>{cart.count}</span></button><button ref={menuButtonRef} className="im-pill" onClick={() => setNavigationOpen(!navigationOpen)} aria-expanded={navigationOpen} aria-controls="im-navigation">{navigationOpen ? 'Cerrar' : 'Menú'} {navigationOpen ? <X size={15}/> : <span>••</span>}</button></div></header>
        {navigationOpen && <nav ref={navigationRef} className="im-navigation" id="im-navigation" aria-label="Navegación principal">{[['inicio','La experiencia'],['carta','Nuestra carta'],['esencia','El origen'],['reservas','Tu próxima mesa']].map(([id,label],index) => <a key={id} href={`#${id}`} onClick={closeNavigation}><small>0{index+1}</small>{label}<ArrowUpRight size={32}/></a>)}<Link href={auth?.user ? '/cuenta' : '/ingresar'} onClick={closeNavigation}><UserRound size={22}/>{auth?.user ? 'Mi cuenta' : 'Ingresar'}<ArrowUpRight size={32}/></Link></nav>}
        <main>
            <section className="im-intro" id="inicio"><div className="im-intro-side"><span>CEVICHERÍA & COCINA MARINA</span><span>PERÚ / HECHO AL MOMENTO</span></div><h1>El Pacífico.<br/>En todos<br/>los <span>sentidos.</span></h1><div className="im-intro-note"><span className="im-live-dot"/><p>Producto fresco. <br/>Instinto peruano. <br/>Un antojo extraordinario.</p></div></section>
            <section className="im-experience" ref={stageRef} onPointerMove={moveScene} onPointerLeave={() => { stageRef.current.style.setProperty('--pointer-x','0deg'); stageRef.current.style.setProperty('--pointer-y','0deg'); }} aria-label="Experiencia interactiva de cocina marina"><OceanScene vivid={vivid} paused={scenePaused}/><div className="im-scene-top"><span>01 / EL ORIGEN DEL ANTOJO</span><div className="im-scene-controls"><button onClick={() => { setScenePaused(!scenePaused); stageRef.current.style.setProperty("--pointer-x", "0deg"); stageRef.current.style.setProperty("--pointer-y", "0deg"); }} aria-pressed={scenePaused}>{scenePaused ? "Reanudar escena" : "Pausar escena"}</button><button onClick={() => setVivid(!vivid)} aria-pressed={vivid}><span className="im-palette-dot"/> Cambiar ambiente</button></div></div><span className="im-scene-word" aria-hidden="true">PURO<br/>MAR.</span><div className="im-floating-plate"><div className="im-plate-inner"><img src="/assets/images/hero_ceviche_plate.png" alt="Ceviche peruano con limón, cebolla morada, choclo y camote" fetchPriority="high"/></div><span className="im-plate-ring"/></div><span className="im-scene-caption">FRESCURA QUE<br/>SE SIENTE.</span><a href="#carta" className="im-scene-cta"><span>Descubre<br/>la carta.</span><span className="im-circle-arrow"><ArrowUpRight size={28}/></span></a><div className="im-scene-bottom"><span>EL SABOR NO SE EXPLICA. SE PRUEBA.</span><span>MUEVE EL CURSOR. DESPIERTA EL ANTOJO.</span></div></section>
            <div className="im-scroll-line"><Plus size={16}/><span>DESLIZA PARA DESCUBRIR</span><Plus size={16}/></div>
            <section className="im-statement" id="esencia" data-reveal><span className="im-eyebrow">EL MAR ES EL PUNTO DE PARTIDA.</span><div><h2>Lo demás es<br/><span>puro instinto.</span></h2><p>Limón recién cortado. El punto justo de ají. El sabor de nuestra costa, preparado al momento. En Desego cocinamos para esos encuentros que empiezan con hambre y terminan en una buena historia.</p><a href="#carta" className="im-pill im-pill-dark">Encuentra tu antojo <ArrowUpRight size={16}/></a></div><Waves size={60} strokeWidth={.8}/></section>
            <section className="im-menu-section" id="carta" aria-labelledby="menu-title">
                <div className="im-section-heading" data-reveal>
                    <div><span className="im-eyebrow">02 / NUESTRA COCINA</span><h2 id="menu-title">La carta<span>.</span></h2><p>El sabor de nuestra costa, preparado al momento.</p></div>
                    <div className="im-service-choice"><span>Elige cómo disfrutarlo</span><div className="im-order-mode" aria-label="Tipo de pedido">{['Delivery','Para llevar'].map(value => <button key={value} aria-pressed={mode===value} onClick={() => setMode(value)}>{value==='Delivery'?'A domicilio':'Para recoger'}</button>)}</div></div>
                </div>
                <div className="im-menu-tools">
                    <div className="im-categories" aria-label="Categorías de la carta">{['all',...categories].map(value => <button key={value} aria-pressed={category===value} onClick={() => setCategory(value)}>{value==='all'?'Toda la carta':value}</button>)}</div>
                    <label className="im-search"><Search size={18}/><input type="search" aria-label="Buscar en la carta" placeholder="Busca tu plato favorito" value={query} onChange={event => setQuery(event.target.value)}/></label>
                </div>
                <div className="im-menu-results" role="status">{filteredProducts.length} {filteredProducts.length===1?'opción para disfrutar':'opciones para disfrutar'}</div>
                <div className="im-menu-grid">
                    {filteredProducts.map(product => <article className="im-product" key={product.id}>
                        <button className="im-product-visual" onClick={() => setSelectedProduct(product)} aria-label={`Ver detalles de ${product.name}`}><img src={foodImage(product)} alt={product.name} loading="lazy"/><span className="im-product-explore" aria-hidden="true"><ArrowUpRight size={21}/></span></button>
                        <div className="im-product-info"><span className="im-eyebrow">{product.category}</span><h3><button onClick={() => setSelectedProduct(product)}>{product.name}</button></h3><p>{product.description || 'Preparado al momento con el sabor de nuestra cocina.'}</p><div className="im-product-footer"><strong>{money(product.price)}</strong><button className="im-add" disabled={product.can_order===false} onClick={() => addProduct(product)} aria-label={product.can_order===false?`${product.name}, no disponible`:`Agregar ${product.name}`}>{product.can_order===false?'No disponible':<><span>Agregar</span><Plus size={18}/></>}</button></div></div>
                    </article>)}
                </div>
                {!filteredProducts.length && <div className="im-empty"><Fish size={35}/><p>{products.length?'No encontramos ese antojo. Prueba otra búsqueda.':'Nuestra carta estará disponible pronto.'}</p>{products.length>0 && <button className="im-pill" onClick={() => { setQuery('');setCategory('all'); }}>Ver toda la carta <ArrowRight size={15}/></button>}</div>}
                <div className="im-menu-footnote"><span>¿Alguna alergia o preferencia? Cuéntanos al hacer tu pedido.</span><button onClick={() => setCartOpen(true)}>Ver mi pedido ({cart.count}) <ArrowUpRight size={16}/></button></div>
            </section>
            <section className="im-interlude" data-reveal><img src="/assets/images/ambience_chef_plating.jpg" alt="El cuidado de nuestra cocina al preparar cada plato" loading="lazy"/><div><span className="im-eyebrow">MANOS QUE SABEN. SABORES QUE SE QUEDAN.</span><h2>Sin prisa.<br/>Con todo<br/><span>el sabor.</span></h2><a className="im-pill" href="#reservas">Nos vemos en la mesa <ArrowUpRight size={18}/></a></div><span className="im-interlude-number">DESEGO / 03</span></section>
            <section className="im-reservation" id="reservas"><div className="im-reservation-intro" data-reveal><span className="im-eyebrow">04 / EL SIGUIENTE BUEN MOMENTO</span><h2>Hagamos<br/><span>un plan.</span></h2><p>Para celebrar, reencontrarse o simplemente comer bien. Elige el día. Nosotros ponemos el sabor.</p>{branch?.address && <div className="im-address"><MapPin size={17}/><span>{branch.address}{branch.district?`, ${branch.district}`:''}</span></div>}{branch?.phone && <a className="im-contact" href={`tel:${branch.phone}`}>{branch.phone}<ArrowUpRight size={16}/></a>}<div className="im-address"><span>¿Ya estás en el local? Escanea el código QR de tu mesa para pedir, llamar al mesero y pagar desde tu celular.</span></div><img src="/assets/images/ambience_dining_room.jpg" alt="Un espacio para compartir una buena comida" loading="lazy"/></div><form className="im-reservation-form" onSubmit={event => { event.preventDefault();reservation.post('/reservar',{preserveScroll:true}); }}><span className="im-eyebrow">TU MESA EMPIEZA AQUÍ.</span><ErrorList errors={reservation.errors}/>{flash?.result?.type==='reservation' && <div className="im-success" role="status"><Check size={20}/><p>Solicitud recibida: <strong>{flash.result.reservation_code}</strong>. El restaurante confirmará tu reserva.</p></div>}<div className="im-form-grid"><label className="im-full">01 / Tu nombre<input required minLength={3} maxLength={100} autoComplete="name" placeholder="¿A nombre de quién?" value={reservation.data.customer_name} onChange={event=>reservation.setData('customer_name',event.target.value)}/></label><label>02 / Fecha<input required type="date" min={today} value={reservation.data.date} onChange={event=>reservation.setData('date',event.target.value)}/></label><label>03 / Hora<input required type="time" value={reservation.data.time} onChange={event=>reservation.setData('time',event.target.value)}/></label><label>04 / Personas<select value={reservation.data.people} onChange={event=>reservation.setData('people',Number(event.target.value))}>{[1,2,3,4,5,6,7,8,10,12,16,20,24].map(number=><option key={number} value={number}>{number} {number===1?'persona':'personas'}</option>)}</select></label><label>05 / Celular<input required type="tel" maxLength={30} autoComplete="tel" placeholder="Tu número" value={reservation.data.phone} onChange={event=>reservation.setData('phone',event.target.value)}/></label><label className="im-full">06 / Correo (opcional)<input type="email" autoComplete="email" maxLength={190} placeholder="hola@tucorreo.com" value={reservation.data.email} onChange={event=>reservation.setData('email',event.target.value)}/></label><label className="im-full">07 / ¿Algo que debamos saber? (opcional)<textarea rows={2} maxLength={500} placeholder="Una celebración, una preferencia…" value={reservation.data.notes} onChange={event=>reservation.setData('notes',event.target.value)}/></label></div><button className="im-reservation-submit" disabled={reservation.processing}>{reservation.processing?'Enviando solicitud…':'Solicitar mi mesa'}<span className="im-circle-arrow"><ArrowUpRight size={27}/></span></button><p className="im-form-note">Tu reserva se completa con la confirmación del restaurante.</p></form></section>
        </main>
        <footer className="im-footer"><div><span className="im-eyebrow">EL MAR NOS VUELVE A ENCONTRAR.</span><a href="#inicio" className="im-footer-cta">¿Repetimos?<ArrowUpRight/></a></div><span className="im-footer-word" aria-hidden="true">DESEGO®</span><div className="im-footer-bottom"><span>© {new Date().getFullYear()} DESEGO · COCINA MARINA PERUANA</span><Link href={auth?.user?'/cuenta':'/ingresar'}>Mi cuenta <ArrowUpRight size={13}/></Link><a href="#carta">La carta <ArrowUpRight size={13}/></a><a href="#inicio">Volver arriba ↑</a></div></footer>
        <Checkout open={cartOpen} close={()=>setCartOpen(false)} cart={cart} mode={mode} setMode={setMode} fee={fee} accountUser={auth?.user} lastOrder={lastOrder}/>
        <dialog ref={detailDialog} className="im-detail-dialog" onCancel={()=>setSelectedProduct(null)} onClose={()=>setSelectedProduct(null)} onClick={event=>{if(event.target===detailDialog.current)setSelectedProduct(null);}}>{selectedProduct && <><button className="im-dialog-close" onClick={()=>setSelectedProduct(null)} aria-label="Cerrar detalle"><X/></button><img src={foodImage(selectedProduct)} alt={selectedProduct.name}/><div><span className="im-eyebrow">{selectedProduct.category}</span><h2>{selectedProduct.name}</h2><p>{selectedProduct.description}</p>{selectedProduct.allergens && <p className="im-allergens">Alérgenos: {selectedProduct.allergens}</p>}<strong>{money(selectedProduct.price)}</strong><button className="im-pill im-pill-dark" disabled={selectedProduct.can_order===false} onClick={()=>{addProduct(selectedProduct);setSelectedProduct(null);}}>{selectedProduct.can_order===false?'No disponible':'Agregar a mi pedido'}<Plus size={18}/></button></div></>}</dialog>
        <div className={`im-toast ${toast?'visible':''}`} role="status"><Check size={17}/><span>{toast}</span><button onClick={()=>{setToast('');setCartOpen(true);}}>Ver pedido <ArrowRight size={15}/></button></div>
        <ChatBot/>
    </div>;
}
