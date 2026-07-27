import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@chevereto/db";
import { auth } from "@/auth";
import { nanoid } from "nanoid";

// Helper to get active user ID since Auth isn't fully wired for MVP
async function getActiveUserId() {
  const session = await auth();
  let userId = session?.user?.id;
  if (!userId) {
    const guestUser = await prisma.user.findUnique({ where: { username: 'guest' } });
    if (guestUser) userId = guestUser.id;
  }
  return userId;
}

export async function GET(req: NextRequest) {
  try {
    const userId = await getActiveUserId();
    if (!userId) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });

    const albums = await prisma.album.findMany({
      where: { userId },
      orderBy: { id: 'desc' }
    });

    return NextResponse.json({ albums });
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const userId = await getActiveUserId();
    if (!userId) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });

    const body = await req.json();
    const { name, description, privacy, password } = body;

    if (!name) return NextResponse.json({ error: "Name is required" }, { status: 400 });

    // Generate a unique slug
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + nanoid(6);

    const album = await prisma.album.create({
      data: {
        userId,
        name,
        slug,
        description,
        privacy: privacy || 'PUBLIC',
        passwordHash: password ? password : null, // In reality, hash this
      }
    });

    return NextResponse.json({ success: true, album });
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }
}
