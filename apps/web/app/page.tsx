import Uploader from "@/components/Uploader";

export default function Home() {
  return (
    <main className="min-h-screen bg-gray-50 flex flex-col items-center pt-24 pb-12 px-4 sm:px-6 lg:px-8">
      <div className="w-full max-w-4xl text-center space-y-8">
        <h1 className="text-5xl font-extrabold text-gray-900 tracking-tight">
          Upload and share your images.
        </h1>
        <p className="text-xl text-gray-600 max-w-2xl mx-auto">
          A fast, simple, and resource-efficient image hosting platform built with Next.js and MinIO.
        </p>
        
        <Uploader />
      </div>
    </main>
  );
}
