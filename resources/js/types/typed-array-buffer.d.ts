/**
 * The VAPID decoder passes a decoded string to TypedArray.from. ECMAScript
 * allocates a fresh ArrayBuffer-backed Uint8Array for this overload, but the
 * generic TypeScript lib declaration widens the result to ArrayBufferLike.
 * Keep the overload narrow so DOM BufferSource consumers stay strictly typed.
 */
interface Uint8ArrayConstructor {
  from(
    arrayLike: string,
    mapfn: (value: string, index: number) => number,
    thisArg?: unknown,
  ): Uint8Array<ArrayBuffer>;
}
