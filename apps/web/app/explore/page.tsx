import { prisma } from "@chevereto/db";
import Link from "next/link";

export default async function ExplorePage() {
  const images = await prisma.image.findMany({
    where: { isPrivate: false },
    orderBy: { likesCount: "desc" },
    take: 100,
    include: { user: true }
  });

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center py-10 px-4">
      <div className="w-full max-w-7xl">
        <h1 className="text-4xl font-extrabold text-gray-900 mb-8">Explore</h1>
        
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          {images.map((img) => (
            <Link key={img.id} href={`/i/${img.id}`} className="group block relative aspect-square bg-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
              <img
                src={img.thumbUrl || img.mediumUrl || ""}
                alt={img.title || img.originalFilename}
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-4">
                <p className="text-white font-medium truncate">{img.user.displayName || img.user.username}</p>
                <p className="text-gray-300 text-sm">❤️ {img.likesCount}</p>
              </div>
            </Link>
          ))}
          {images.length === 0 && (
            <div className="col-span-full py-20 text-center text-gray-500">
              No public images found to explore.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
