import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@chevereto/db";
import { auth } from "@/auth";

export async function POST(req: NextRequest) {
  try {
    const session = await auth();
    let userId = session?.user?.id;
    if (!userId) {
      const guest = await prisma.user.findUnique({ where: { username: 'guest' } });
      userId = guest?.id;
    }
    if (!userId) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });

    const { imageId } = await req.json();

    const existing = await prisma.like.findFirst({
      where: { userId, imageId }
    });

    if (existing) {
      await prisma.like.delete({ where: { id: existing.id } });
      await prisma.image.update({ where: { id: imageId }, data: { likesCount: { decrement: 1 } } });
      return NextResponse.json({ liked: false });
    } else {
      await prisma.like.create({ data: { userId, imageId } });
      await prisma.image.update({ where: { id: imageId }, data: { likesCount: { increment: 1 } } });
      return NextResponse.json({ liked: true });
    }
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 });
  }
}
