import type { APIRoute } from "astro";
import { sitemapIndexXml, xmlResponse } from "../lib/seo-files";

export const GET: APIRoute = () => xmlResponse(sitemapIndexXml());
