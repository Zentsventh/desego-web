import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { diningApi } from '../../lib/dining';
import '../../../css/dining.css';

export default function TablePayment(){
    const [form,setForm]=useState({scope:'mine',email:'',phone:'',address:'',city:'Lima',postal_code:''}),[error,setError]=useState(''),[busy,setBusy]=useState(false);
    async function submit(event){event.preventDefault();setBusy(true);setError('');try{const token=window.location.hash.slice(1);const result=await diningApi('payments',{token,method:'POST',body:form});window.location.assign(result.checkout_url);}catch(e){setError(e.message);setBusy(false);}}
    return <main className="dining-client dining-payment-start"><Head title="Pago de tu mesa"/><header className="dining-client-header"><a href="/">DESEGO</a></header><section className="dining-welcome"><span className="dining-eyebrow">NIUBIZ · PAGO SEGURO</span><h1>El último detalle<br/>de un buen momento.</h1><p>Los datos de contacto y facturación se envían a Niubiz para validar el pago. La tarjeta se ingresa directamente en su formulario.</p>{error&&<p role="alert" className="dining-error">{error}</p>}<form onSubmit={submit}><label>¿Qué quieres pagar?<select value={form.scope} onChange={e=>setForm({...form,scope:e.target.value})}><option value="mine">Solo mis pedidos</option><option value="all">Toda la mesa</option></select></label>{[['email','Correo electrónico','email'],['phone','Celular','tel'],['address','Dirección de facturación','text'],['city','Ciudad o distrito','text'],['postal_code','Código postal','text']].map(([field,label,type])=><label key={field}>{label}<input required type={type} maxLength={field==='email'?80:field==='address'?60:field==='city'?50:field==='postal_code'?9:15} value={form[field]} onChange={e=>setForm({...form,[field]:e.target.value})}/></label>)}<button disabled={busy}>{busy?'Conectando con Niubiz…':'Continuar al pago seguro'}</button></form></section></main>;
}
