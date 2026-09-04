/**
 * ECMAScript TypedArray.from allocates a fresh ArrayBuffer-backed typed array.
 * TypeScript's generic lib declaration widens that result to ArrayBufferLike,
 * which is too broad for DOM BufferSource consumers such as PushManager.
 */
interface Uint8ArrayConstructor {
  from<T>(
    arrayLike: ArrayLike<T>,
    mapfn: (value: T, index: number) => number,
    thisArg?: unknown,
  ): Uint8Array<ArrayBuffer>;
}
