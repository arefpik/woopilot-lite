export default function StatTile({ label, value, accent }) {
  return (
    <div className="rounded-xl border border-black/10 bg-white p-5 shadow-sm">
      <p className="text-sm text-[#52514e]">{label}</p>
      <p className="mt-1 text-3xl font-semibold text-[#0b0b0b]">{value}</p>
      <span
        className="mt-3 block h-1 w-8 rounded-full"
        style={{ backgroundColor: accent }}
        aria-hidden="true"
      />
    </div>
  );
}
