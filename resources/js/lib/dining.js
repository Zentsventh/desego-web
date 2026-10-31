/** Version 5-L, byte mode. Los datos del QR se procesan localmente. */
export function qrMatrix(text) {
    const bytes = new TextEncoder().encode(text);
    if (bytes.length > 106) throw new Error('El enlace es demasiado largo para esta etiqueta QR. Configura un dominio más corto.');
    const size = 37;
    const matrix = Array.from({ length: size }, () => Array(size).fill(null));
    const fixed = Array.from({ length: size }, () => Array(size).fill(false));
    function set(x, y, value) { if (x >= 0 && y >= 0 && x < size && y < size) { matrix[y][x] = value ? 1 : 0; fixed[y][x] = true; } }
    for (const [left, top] of [[0,0],[30,0],[0,30]]) {
        for (let y=-1;y<=7;y++) for(let x=-1;x<=7;x++) set(left+x,top+y,x>=0&&x<=6&&y>=0&&y<=6&&(x===0||x===6||y===0||y===6||(x>=2&&x<=4&&y>=2&&y<=4)));
    }
    for(let i=8;i<29;i++){set(i,6,i%2===0);set(6,i,i%2===0);}
    for(let y=-2;y<=2;y++)for(let x=-2;x<=2;x++)set(30+x,30+y,Math.max(Math.abs(x),Math.abs(y))!==1);
    let format=8<<10;
    for(let i=14;i>=10;i--)if((format>>>i)&1)format^=0x537<<(i-10);
    format=((8<<10)|format)^0x5412;
    for(let i=0;i<15;i++){
        const bit=(format>>>i)&1;
        if(i<6)set(8,i,bit);else if(i<8)set(8,i+1,bit);else set(8,size-15+i,bit);
        if(i<8)set(size-i-1,8,bit);else if(i<9)set(15-i,8,bit);else set(14-i,8,bit);
    }
    set(8,29,1);
    const bits=[];
    function push(value,count){for(let i=count-1;i>=0;i--)bits.push((value>>>i)&1);}
    push(4,4);push(bytes.length,8);bytes.forEach(byte=>push(byte,8));
    push(0,Math.min(4,864-bits.length));while(bits.length%8)bits.push(0);
    const data=[];for(let i=0;i<bits.length;i+=8)data.push(bits.slice(i,i+8).reduce((a,b)=>(a<<1)|b,0));
    while(data.length<108)data.push(data.length%2===0?0xec:0x11);
    const exp=Array(512),log=Array(256).fill(0);let value=1;
    for(let i=0;i<255;i++){exp[i]=value;log[value]=i;value<<=1;if(value&256)value^=0x11d;}
    for(let i=255;i<512;i++)exp[i]=exp[i-255];
    const mul=(a,b)=>a&&b?exp[log[a]+log[b]]:0;
    let polynomial=[1];for(let i=0;i<26;i++){const next=Array(polynomial.length+1).fill(0);polynomial.forEach((v,j)=>{next[j]^=v;next[j+1]^=mul(v,exp[i]);});polynomial=next;}
    const remainder=Array(26).fill(0);
    for(const byte of data){const factor=byte^remainder.shift();remainder.push(0);for(let i=0;i<26;i++)remainder[i]^=mul(polynomial[i+1],factor);}
    const stream=[];for(const byte of [...data,...remainder])for(let i=7;i>=0;i--)stream.push((byte>>>i)&1);
    let index=0,up=true;
    for(let right=size-1;right>0;right-=2){if(right===6)right--;for(let row=0;row<size;row++){const y=up?size-1-row:row;for(let dx=0;dx<2;dx++){const x=right-dx;if(!fixed[y][x])matrix[y][x]=((stream[index++]||0)^((x+y)%2===0?1:0));}}up=!up;}
    return matrix;
}

export async function diningApi(path, { token, method='GET', body } = {}) {
    const response = await fetch(`/api/mobile/dining/${path}`, { method, credentials:'same-origin', headers:{ Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || '', ...(token?{'X-Dining-Token':token}:{}) }, ...(body?{body:JSON.stringify(body)}:{}) });
    const result=await response.json();
    if(!response.ok){ const first=Object.values(result.errors||{}).flat()[0]; throw new Error(first||result.message||'No se pudo completar la solicitud.'); }
    return result;
}
