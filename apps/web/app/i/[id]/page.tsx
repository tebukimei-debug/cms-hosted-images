import { prisma } from "@chevereto/db";
import { notFound } from "next/navigation";
import Image from "next/image";
import Link from "next/link";

export default async function ImageViewer({ params }: { params: { id: string } }) {
  const imageId = params.id;

  const image = await prisma.image.findUnique({
    where: { id: imageId },
    include: { user: true },
  });

  if (!image) {
    notFound();
  }

  // Increment view count asynchronously
  prisma.image.update({
    where: { id: imageId },
    data: { viewsCount: { increment: 1 } },
  }).catch(console.error);

  return (
    <div className="min-h-screen bg-gray-900 text-white flex flex-col items-center py-10 px-4">
      <div className="w-full max-w-6xl">
        {/* Header */}
        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-2xl font-bold">{image.originalFilename}</h1>
            <p className="text-gray-400 text-sm mt-1">
              Uploaded by{" "}
              <Link href={`/u/${image.user.username}`} className="text-blue-400 hover:underline">
                {image.user.displayName || image.user.username}
              </Link>
            </p>
          </div>
          <div className="flex space-x-4">
            <span className="text-gray-400 text-sm">👁️ {image.viewsCount + 1} views</span>
            <span className="text-gray-400 text-sm">{Math.round(image.sizeBytes / 1024)} KB</span>
            <span className="text-gray-400 text-sm">{image.width} × {image.height}</span>
          </div>
        </div>

        {/* Image Display */}
        <div className="relative w-full flex justify-center bg-black/50 rounded-xl overflow-hidden shadow-2xl p-4">
          <img
            src={image.mediumUrl || ""}
            alt={image.title || image.originalFilename}
            className="max-w-full max-h-[80vh] object-contain"
          />
        </div>
        
        {/* Actions */}
        <div className="mt-8 flex justify-center space-x-4">
          <a
            href={image.mediumUrl || ""}
            download={image.originalFilename}
            target="_blank"
            rel="noopener noreferrer"
            className="px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition-colors"
          >
            Download Original
          </a>
        </div>
      </div>
    </div>
  );
}
