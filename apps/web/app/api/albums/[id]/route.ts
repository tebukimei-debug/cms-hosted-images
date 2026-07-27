import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@chevereto/db";
import { auth } from "@/auth";

async function getActiveUserId() {
  const session = await auth();
  let userId = session?.user?.id;
  if (!userId) {
    const guestUser = await prisma.user.findUnique({ where: { username: 'guest' } });
    if (guestUser) userId = guestUser.id;
  }
  return userId;
}

export async function PUT(req: NextRequest, { params }: { params: { id: string } }) {
  try {
    const userId = await getActiveUserId();
    if (!userId) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });

    const albumId = params.id;
    const album = await prisma.album.findUnique({ where: { id: albumId } });

    if (!album || album.userId !== userId) {
      return NextResponse.json({ error: "Forbidden" }, { status: 403 });
    }

    const body = await req.json();
    const { name, description, privacy, password } = body;

    const updatedAlbum = await prisma.album.update({
      where: { id: albumId },
      data: {
        ...(name && { name }),
        ...(description !== undefined && { description }),
        ...(privacy && { privacy }),
        ...(password !== undefined && { passwordHash: password ? password : null }),
      }
    });

    return NextResponse.json({ success: true, album: updatedAlbum });
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }
}

export async function DELETE(req: NextRequest, { params }: { params: { id: string } }) {
  try {
    const userId = await getActiveUserId();
    if (!userId) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });

    const albumId = params.id;
    const album = await prisma.album.findUnique({ where: { id: albumId } });

    if (!album || album.userId !== userId) {
      return NextResponse.json({ error: "Forbidden" }, { status: 403 });
    }

    await prisma.album.delete({ where: { id: albumId } });

    return NextResponse.json({ success: true });
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }
}
