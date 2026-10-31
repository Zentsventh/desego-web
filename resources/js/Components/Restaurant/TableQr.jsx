import React, { useMemo } from 'react';
import { qrMatrix } from '../../lib/dining';

export default function TableQr({ value, label }) {
    const result=useMemo(()=>{try{return {matrix:qrMatrix(value)};}catch(error){return {error:error.message};}},[value]);
    if(result.error)return <p role="alert">{result.error}</p>;
    const path=result.matrix.flatMap((row,y)=>row.map((dark,x)=>dark?`M${x+4} ${y+4}h1v1h-1z`:'')).join('');
    return <svg viewBox="0 0 45 45" role="img" aria-label={`Código QR de ${label}`} className="dining-qr" shapeRendering="crispEdges"><rect width="45" height="45" fill="white"/><path d={path} fill="#111827"/></svg>;
}
