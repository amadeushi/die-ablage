/** Ignore diagonal gestures, short taps and panning within a zoomed PDF. */
export function pdfSwipeDirection(dx:number,dy:number,zoom:number): -1|0|1 {
 if(zoom>1.01||Math.abs(dx)<60||Math.abs(dx)<Math.abs(dy)*1.5)return 0;
 return dx<0?1:-1;
}
