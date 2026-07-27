import { prisma } from "@chevereto/db";
import { notFound } from "next/navigation";
import Link from "next/link";

export default async function UserProfile({ params }: { params: { username: string } }) {
  const username = params.username;

  const user = await prisma.user.findUnique({
    where: { username },
    include: {
      images: {
        orderBy: { createdAt: "desc" },
        take: 50,
      },
    },
  });

  if (!user) {
    notFound();
  }

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center py-10 px-4">
      <div className="w-full max-w-6xl">
        {/* Profile Header */}
        <div className="bg-white rounded-2xl shadow-sm p-8 mb-8 text-center flex flex-col items-center">
          <div className="w-24 h-24 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-4xl font-bold mb-4">
            {user.username.charAt(0).toUpperCase()}
          </div>
          <h1 className="text-3xl font-bold text-gray-900">{user.displayName || user.username}</h1>
          <p className="text-gray-500 mt-2">@{user.username}</p>
          {user.bio && <p className="text-gray-700 mt-4 max-w-lg">{user.bio}</p>}
          <div className="mt-6 flex space-x-6 text-sm text-gray-600">
            <div><span className="font-bold text-gray-900">{user.images.length}</span> Images</div>
            {/* Albums will be added here later */}
          </div>
        </div>

        {/* Image Grid */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {user.images.map((img) => (
            <Link key={img.id} href={`/i/${img.id}`} className="group block relative aspect-square bg-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
              <img
                src={img.thumbUrl || img.mediumUrl || ""}
                alt={img.title || img.originalFilename}
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              />
              <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors" />
            </Link>
          ))}
          {user.images.length === 0 && (
            <div className="col-span-full py-20 text-center text-gray-500">
              No images uploaded yet.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
