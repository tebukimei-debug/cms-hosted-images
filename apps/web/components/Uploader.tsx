"use client";

import { useState, useCallback, useEffect } from "react";
import { useRouter } from "next/navigation";

export default function Uploader() {
  const [isDragging, setIsDragging] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const [albums, setAlbums] = useState<any[]>([]);
  const [selectedAlbum, setSelectedAlbum] = useState<string>("");
  const [privacy, setPrivacy] = useState<string>("PUBLIC");
  
  const router = useRouter();

  useEffect(() => {
    // Fetch user albums on mount (fails gracefully if unauthenticated)
    fetch("/api/albums")
      .then(res => res.json())
      .then(data => {
        if (data.albums) setAlbums(data.albums);
      })
      .catch(console.error);
  }, []);

  const handleUpload = async (file: File) => {
    if (!file.type.startsWith("image/")) {
      setError("Please upload an image file.");
      return;
    }

    setIsUploading(true);
    setError(null);

    const formData = new FormData();
    formData.append("file", file);
    if (selectedAlbum) formData.append("albumId", selectedAlbum);
    if (privacy) formData.append("privacy", privacy);

    try {
      const res = await fetch("/api/upload", {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || "Upload failed");
      }

      router.push(`/i/${data.image.id}`);
    } catch (err: any) {
      setError(err.message);
    } finally {
      setIsUploading(false);
    }
  };

  const onDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  }, []);

  const onDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  }, []);

  const onDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handleUpload(e.dataTransfer.files[0]);
    }
  }, []);

  return (
    <div className="w-full max-w-2xl mx-auto mt-10">
      <div className="flex gap-4 mb-4">
        <select 
          className="flex-1 p-2 border border-gray-300 rounded-lg text-black bg-white"
          value={selectedAlbum}
          onChange={e => setSelectedAlbum(e.target.value)}
        >
          <option value="">No Album (Direct Upload)</option>
          {albums.map(a => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>

        <select 
          className="flex-1 p-2 border border-gray-300 rounded-lg text-black bg-white"
          value={privacy}
          onChange={e => setPrivacy(e.target.value)}
        >
          <option value="PUBLIC">Public</option>
          <option value="PRIVATE">Private</option>
          <option value="UNLISTED">Unlisted</option>
        </select>
      </div>

      <div
        className={`relative border-2 border-dashed rounded-xl p-12 text-center transition-colors ${
          isDragging
            ? "border-blue-500 bg-blue-50"
            : "border-gray-300 bg-gray-50 hover:bg-gray-100"
        }`}
        onDragOver={onDragOver}
        onDragLeave={onDragLeave}
        onDrop={onDrop}
      >
        <input
          type="file"
          accept="image/*"
          className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
          onChange={(e) => {
            if (e.target.files && e.target.files.length > 0) {
              handleUpload(e.target.files[0]);
            }
          }}
          disabled={isUploading}
        />
        <div className="space-y-4">
          <div className="text-4xl">📸</div>
          <h3 className="text-lg font-semibold text-gray-700">
            {isUploading ? "Uploading..." : "Drag and drop or click to upload"}
          </h3>
          <p className="text-sm text-gray-500">
            Support for JPG, PNG, WEBP, GIF
          </p>
        </div>
      </div>
      {error && (
        <div className="mt-4 p-4 bg-red-50 text-red-600 rounded-lg text-center">
          {error}
        </div>
      )}
    </div>
  );
}
