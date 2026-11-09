import type { APIRoute } from "astro";
import { robotsTxt } from "../lib/seo-files";

export const GET: APIRoute = () =>
  new Response(robotsTxt(), { status: 200, headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=3600" } });
