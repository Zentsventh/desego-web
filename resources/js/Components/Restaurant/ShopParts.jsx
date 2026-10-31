import React, { useEffect, useRef, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import Carousel from 'bootstrap/js/dist/carousel';
import { ArrowUpRight, ArrowRight, ArrowLeft, ShoppingBag, Plus, Minus, X, Heart, Fish, Waves, Leaf, Truck, Check, Pause, Play } from './Icons';

export const money = value => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(value || 0));
export function Brand({ light = false }) {
    return <Link href="/" className={`dg-brand ${light ? 'dg-brand-light' : ''}`} aria-label="DESEGO, inicio"><Fish strokeWidth={1.2} size={39}/><span>DESEGO<small>CEVICHERÍA & COCINA MARINA</small></span></Link>;
}
export function ErrorList({ errors }) {
    return Object.keys(errors || {}).length ? <div className="dg-error" role="alert">{Object.entries(errors).map(([key,value]) => <p key={key}>{value}</p>)}</div> : null;
}
export function useAtmosphere() {
    useEffect(() => {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let frame = 0;
        const move = () => {
            if (frame || reduced.matches) return;
            frame = requestAnimationFrame(() => {
                document.querySelectorAll('[data-parallax]').forEach(element => {
                    const rect = element.parentElement.getBoundingClientRect();
                    if (rect.bottom > 0 && rect.top < window.innerHeight) element.style.setProperty('--drift', `${Math.max(-130, Math.min(130, -rect.top * Number(element.dataset.parallax)))}px`);
                });
                frame = 0;
            });
        };
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add('dg-visible'); observer.unobserve(entry.target); }
        }), { threshold: 0.08 });
        document.querySelectorAll('[data-reveal]').forEach(el => observer.observe(el));
        window.addEventListener('scroll', move, { passive: true }); move();
        return () => { observer.disconnect(); window.removeEventListener('scroll', move); cancelAnimationFrame(frame); };
    }, []);
}
export function Hero({ products }) {
    const element = useRef(null), carousel = useRef(null);
    const [active, setActive] = useState(0), [paused, setPaused] = useState(false);
    const slides = [
        { image: 'ceviche', label: 'El sabor que nos encuentra', title: <>El mar, servido<br/>con <em>alma.</em></>, description: 'Fresco, intenso, nuestro. Descubre una cocina que celebra el sabor de la costa peruana.', dish: products.find(p => /ceviche/i.test(p.name)) },
        { image: 'arroz', label: 'Un encuentro con nuestra costa', title: <>La buena mesa<br/>se <em>comparte.</em></>, description: 'Texturas, aromas y ese toque de casa. Hay sabores que merecen disfrutarse sin prisa.', dish: products.find(p => /arroz/i.test(p.name)) },
        { image: 'pescado', label: 'Del primer bocado al último', title: <>Simplemente<br/><em>irresistible.</em></>, description: 'El mar inspira. Nuestra cocina lo transforma en ese plato al que siempre quieres volver.', dish: products.find(p => /frito/i.test(p.name)) },
    ];
    useEffect(() => {
        const node = element.current;
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        carousel.current = new Carousel(node, { interval: reduced ? false : 7000, ride: reduced ? false : 'carousel', pause: 'hover', touch: true });
        const slide = event => setActive(event.to);
        node.addEventListener('slide.bs.carousel', slide);
        return () => { node.removeEventListener('slide.bs.carousel', slide); carousel.current?.dispose(); };
    }, []);
    return <section className="dg-hero" aria-roledescription="carrusel" aria-label="Sabores de DESEGO">
        <div className="carousel slide carousel-fade dg-hero-carousel" ref={element}><div className="carousel-inner">
            {slides.map((slide,index) => <div key={slide.image} className={`carousel-item ${index === 0 ? 'active' : ''}`} aria-hidden={active !== index}>
                <img className="dg-hero-image" data-parallax="0.16" src={`/images/menu/food_${slide.image}.jpg`} alt={slide.dish?.name || 'Cocina marina peruana'} fetchPriority={index === 0 ? 'high' : 'auto'}/><div className="dg-hero-shade"/>
                <div className="dg-container dg-hero-copy"><span className="dg-eyebrow dg-eyebrow-light"><span/> {slide.label}</span><h1>{slide.title}</h1><p>{slide.description}</p><a href="#carta" className="dg-button dg-button-ochre">Explorar la carta <ArrowUpRight size={19}/></a></div>
            </div>)}
        </div></div>
        <div className="dg-hero-stamp"><Waves size={33} strokeWidth={1}/><span>ESENCIA PERUANA</span><small>Hecho al momento</small></div>
        <div className="dg-container dg-hero-bottom"><span className="dg-hero-caption"><span className="dg-hairline"/> {slides[active].dish?.name || 'Nuestra cocina marina'}</span><div className="dg-slider-controls"><button aria-label="Anterior" onClick={() => carousel.current?.prev()}><ArrowLeft size={18}/></button><span>0{active + 1} <i>/ 03</i></span><button aria-label="Siguiente" onClick={() => carousel.current?.next()}><ArrowRight size={18}/></button><button aria-label={paused ? 'Reproducir carrusel' : 'Pausar carrusel'} onClick={() => { paused ? carousel.current?.cycle() : carousel.current?.pause(); setPaused(!paused); }}>{paused ? <Play size={14}/> : <Pause size={14}/>}</button></div><a href="#carta" className="dg-scroll-hint">DESLIZA PARA DESCUBRIR <ArrowRight size={15}/></a></div>
    </section>;
}
export function Product({ product, favorite, onFavorite, onAdd, onOpen }) {
    return <article className="dg-product" data-reveal><button className="dg-product-photo" onClick={() => onOpen(product)} aria-label={`Ver ${product.name}`}><img src={product.image} alt={product.name} loading="lazy"/><span className="dg-product-category">{product.category}</span></button><button className={`dg-heart ${favorite ? 'is-favorite' : ''}`} onClick={() => onFavorite(product.id)} aria-label={favorite ? `Quitar ${product.name} de favoritos` : `Guardar ${product.name}`} aria-pressed={favorite}><Heart size={18} fill={favorite ? 'currentColor' : 'none'}/></button><div className="dg-product-description"><div className="dg-product-title"><h3><button onClick={() => onOpen(product)}>{product.name}</button></h3><span>{money(product.price)}</span></div><p>{product.description || 'Preparado al momento con el sabor de nuestra cocina.'}</p><div className="dg-product-bottom"><span>{product.allergens ? <><Leaf size={13}/> Ver ingredientes</> : 'Sabor de nuestra costa'}</span><button onClick={() => onAdd(product.id)} disabled={!product.can_order} aria-label={`Agregar ${product.name}`} className="dg-add">{product.can_order ? <Plus size={20}/> : <span>No disponible</span>}</button></div></div></article>;
}
export function Checkout({ open, close, cart, mode, setMode, fee, accountUser, lastOrder }) {
    const dialog = useRef(null); const { flash } = usePage().props;
    const [stage,setStage] = useState('cart'), [quote,setQuote] = useState(null);
    const form = useForm({ customer_name: accountUser?.name || '', email: accountUser?.email || '', phone: '', address_line: '', district: '', province: 'Lima', department: 'Lima', reference: '', notes: '', consent: false, payment_preference: 'Efectivo', coupon_code: '', checkout_key: crypto.randomUUID(), order_type: mode, items: [] });
    const signature = JSON.stringify(cart.items.map(item => ({ product_id:item.product.id, quantity:item.quantity })));
    useEffect(() => { form.setData(data => ({ ...data, items: JSON.parse(signature), order_type: mode })); setQuote(null); }, [signature, mode, form.data.coupon_code]);
    useEffect(() => { if (open && !dialog.current.open) dialog.current.showModal(); else if (!open && dialog.current.open) dialog.current.close(); }, [open]);
    useEffect(() => { if (flash?.result?.type === 'order') { setStage('success'); cart.clear(); form.setData('checkout_key',crypto.randomUUID()); } if (flash?.result?.type === 'quote') setQuote(flash.result.quote); }, [flash?.result]);
    const delivery = mode === 'Delivery' ? fee : 0;
    const total = quote?.total ?? cart.subtotal + delivery;
    return <dialog ref={dialog} className="dg-cart" onCancel={close} onClose={close} onClick={event => { if (event.target === dialog.current) close(); }}><div className="dg-cart-inside"><header><div><span className="dg-eyebrow">DESEGO EN TU MESA</span><h2>{stage === 'success' ? 'Buen provecho.' : stage === 'checkout' ? 'Ya casi es tuyo.' : 'Tu buen antojo.'}</h2></div><button className="dg-icon" onClick={close} aria-label="Cerrar carrito"><X/></button></header>
    {stage === 'success' ? <div className="dg-order-success"><span className="dg-success-icon"><Check size={38}/></span><h3>Tu pedido fue recibido</h3><p>{lastOrder?.order_number || flash?.result?.order_number}</p><span className="dg-status">{lastOrder?.status || 'Pendiente'}</span><p>El restaurante ya tiene tu pedido. El pago se coordina con el medio que elegiste.</p><button className="dg-button" onClick={() => router.reload({only:['lastOrder'],preserveScroll:true})}>Consultar estado <ArrowRight size={16}/></button><button className="dg-text-button" onClick={() => { setStage('cart'); close(); }}>Seguir explorando</button></div> : <>
    {stage === 'cart' ? <><div className="dg-cart-lines">{cart.items.length ? cart.items.map(({product,quantity}) => <div className="dg-cart-line" key={product.id}><img src={product.image} alt=""/><div><h3>{product.name}</h3><span>{money(product.price)}</span><div className="dg-quantity"><button onClick={() => cart.setQuantity(product.id,quantity-1)} aria-label={`Quitar una unidad de ${product.name}`}><Minus size={13}/></button><span>{quantity}</span><button onClick={() => cart.setQuantity(product.id,quantity+1)} aria-label={`Sumar una unidad de ${product.name}`} disabled={quantity >= 20}><Plus size={13}/></button></div></div><strong>{money(product.price * quantity)}</strong><button className="dg-icon" aria-label={`Eliminar ${product.name}`} onClick={() => cart.setQuantity(product.id,0)}><X size={14}/></button></div>) : <div className="dg-empty"><ShoppingBag size={44} strokeWidth={1}/><h3>Un buen momento empieza aquí.</h3><p>Agrega tus favoritos de la carta.</p><button className="dg-button" onClick={close}>Descubrir la carta <ArrowUpRight size={16}/></button></div>}</div>{cart.count > 0 && <><div className="dg-mode"><button className={mode==='Delivery'?'selected':''} onClick={() => setMode('Delivery')}><Truck size={17}/> Delivery</button><button className={mode!=='Delivery'?'selected':''} onClick={() => setMode('Para llevar')}><ShoppingBag size={17}/> Recoger</button></div><div className="dg-coupon"><input aria-label="Código de cupón" placeholder="¿Tienes un código de descuento?" value={form.data.coupon_code} onChange={e=>form.setData('coupon_code',e.target.value.toUpperCase())}/><button disabled={form.processing} onClick={()=>form.post('/carrito/cotizar',{preserveScroll:true})}>Aplicar</button></div><ErrorList errors={form.errors}/></>}</> : <form id="dg-checkout-form" className="dg-form" onSubmit={e=>{e.preventDefault();form.post('/pedido',{preserveScroll:true});}}><button type="button" className="dg-text-button" onClick={()=>setStage('cart')}><ArrowLeft size={15}/> Volver al carrito</button><ErrorList errors={form.errors}/><label>Nombre completo<input autoComplete="name" required value={form.data.customer_name} onChange={e=>form.setData('customer_name',e.target.value)}/></label><div className="dg-form-grid"><label>Celular<input type="tel" autoComplete="tel" required value={form.data.phone} onChange={e=>form.setData('phone',e.target.value)}/></label><label>Correo electrónico<input type="email" autoComplete="email" value={form.data.email} onChange={e=>form.setData('email',e.target.value)}/></label></div>{mode==='Delivery' && <><label>Dirección de entrega<input autoComplete="street-address" required minLength={5} value={form.data.address_line} onChange={e=>form.setData('address_line',e.target.value)}/></label><label>Distrito<input required value={form.data.district} onChange={e=>form.setData('district',e.target.value)}/></label><label>Referencia<input value={form.data.reference} onChange={e=>form.setData('reference',e.target.value)}/></label></>}<label>Cómo prefieres pagar<select value={form.data.payment_preference} onChange={e=>form.setData('payment_preference',e.target.value)}><option>Efectivo</option><option>Yape/Plin</option><option>Tarjeta al recibir</option></select></label><label>¿Algo que debamos saber?<textarea maxLength={300} rows={2} placeholder="Indicaciones para tu pedido" value={form.data.notes} onChange={e=>form.setData('notes',e.target.value)}/></label><label className="dg-check"><input type="checkbox" required checked={form.data.consent} onChange={e=>form.setData('consent',e.target.checked)}/><span>Autorizo el uso de mis datos para gestionar y entregar este pedido.</span></label><p className="dg-small">El precio y la disponibilidad se validan al confirmar. No realizamos cargos automáticos.</p></form>}
    {cart.count > 0 && <footer className="dg-cart-total"><div><span>Subtotal</span><strong>{money(cart.subtotal)}</strong></div><div><span>{mode==='Delivery'?'Delivery':'Recojo en el local'}</span><span>{money(delivery)}</span></div>{quote?.discount > 0 && <div><span>Descuento</span><span>- {money(quote.discount)}</span></div>}<div className="dg-grand-total"><span>Total</span><strong>{money(total)}</strong></div>{stage==='cart' ? <button className="dg-button" onClick={()=>setStage('checkout')}>Continuar con mi pedido <ArrowRight size={18}/></button> : <button className="dg-button" type="submit" form="dg-checkout-form" disabled={form.processing}>{form.processing?'Confirmando...':'Confirmar pedido'} <ArrowRight size={18}/></button>}</footer>}</>}
    </div></dialog>;
}
