export default function StatTileSkeleton() {
  return (
    <div className="animate-pulse rounded-xl border border-black/10 bg-white p-5 shadow-sm">
      <div className="h-3 w-24 rounded bg-gray-200" />
      <div className="mt-3 h-8 w-16 rounded bg-gray-200" />
      <div className="mt-3 h-1 w-8 rounded-full bg-gray-200" />
    </div>
  );
}
