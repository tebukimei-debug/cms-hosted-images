import { NextRequest, NextResponse } from "next/server";
import { uploadFile } from "@/lib/storage";
import { prisma } from "@chevereto/db";
import sharp from "sharp";
import { nanoid } from "nanoid";
import { auth } from "@/auth";

export async function POST(req: NextRequest) {
  try {
    const session = await auth();
    // Default to a guest user or handle anonymous uploads depending on settings
    const userId = session?.user?.id || undefined;

    const formData = await req.formData();
    const file = formData.get("file") as File;
    const albumId = formData.get("albumId") as string | null;
    const categoryId = formData.get("categoryId") as string | null;
    const privacyParam = formData.get("privacy") as string | null;

    if (!file) {
      return NextResponse.json({ error: "No file provided" }, { status: 400 });
    }

    const buffer = Buffer.from(await file.arrayBuffer());
    
    // Process image with Sharp
    const image = sharp(buffer);
    const metadata = await image.metadata();

    if (!metadata.width || !metadata.height) {
      return NextResponse.json({ error: "Invalid image format" }, { status: 400 });
    }

    const uniqueId = nanoid(10);
    const extension = file.name.split('.').pop() || 'jpg';
    
    const originalKey = `originals/${uniqueId}.${extension}`;

    // Upload original to MinIO (Wait for it so we can create DB record)
    const originalUrl = await uploadFile(buffer, originalKey, file.type);

    let activeUserId = userId;
    if (!activeUserId) {
      const guestUser = await prisma.user.upsert({
        where: { username: 'guest' },
        update: {},
        create: {
          username: 'guest',
          displayName: 'Guest User',
          role: 'GUEST',
        }
      });
      activeUserId = guestUser.id;
    }

    const isPrivate = privacyParam === 'PRIVATE' || privacyParam === 'PASSWORD';

    // Save to Database (thumbUrl is null initially)
    const imageRecord = await prisma.image.create({
      data: {
        id: uniqueId,
        userId: activeUserId,
        albumId: albumId || null,
        categoryId: categoryId || null,
        originalFilename: file.name,
        storageKey: originalKey,
        width: metadata.width,
        height: metadata.height,
        sizeBytes: file.size,
        mimeType: file.type,
        mediumUrl: originalUrl, 
        isPrivate: isPrivate,
      }
    });

    // Enqueue job for background processing
    const { imageQueue } = await import('@/lib/queue');
    await imageQueue.add("process-image", { imageId: uniqueId });

    return NextResponse.json({ success: true, image: imageRecord });
  } catch (error: any) {
    console.error("Upload error:", error);
    return NextResponse.json({ error: error.message || "Internal server error" }, { status: 500 });
  }
}
