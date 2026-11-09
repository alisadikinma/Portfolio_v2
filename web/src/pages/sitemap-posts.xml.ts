import type { APIRoute } from "astro";
import { proxySitemap } from "../lib/seo-files";

export const GET: APIRoute = () => proxySitemap("/sitemap-posts.xml");
