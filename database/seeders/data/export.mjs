// Exports the frontend's mock data to JSON for the seeders (Phase 2, docs/BACKEND_PLAN.md).
//
// Dev-only, plain Node (no npm, no dependencies; Hostinger never runs it):
//   composer seed:export
//   node --experimental-strip-types database/seeders/data/export.mjs [path/to/restaurant-template]
//
// It imports ../restaurant-template/src/data/mock/*.ts READ-ONLY (Node strips the
// `import type` lines), writes one JSON file per area next to this script, and
// copies the photos the JSON uses into ./images. Re-run it whenever the mock
// data changes, then review the diff and commit. It refuses (exit code 1) when:
//   - a mock file imports anything but types (it would run frontend code),
//   - an expected export is missing,
//   - a branch staff phone isn't one of the restaurant's public numbers
//     (real staff numbers must never be committed),
//   - a photo the data uses doesn't exist.

import { execFileSync } from "node:child_process";
import { copyFile, mkdir, readdir, readFile, rm, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, "../../..");
const frontend = path.resolve(process.argv[2] ?? path.join(repoRoot, "../restaurant-template"));
const mockDir = path.join(frontend, "src/data/mock");
const imagesDir = path.join(here, "images");

/** Output file → the mock file and the exports it must have. */
const SOURCES = {
    menu: { file: "menu.ts", exports: ["categories", "products"] },
    offers: { file: "offers.ts", exports: ["offers"] },
    banners: { file: "menu-banners.ts", exports: ["menuBanners"] },
    reviews: { file: "reviews.ts", exports: ["reviews"] },
    hero: { file: "hero.ts", exports: ["hero"] },
    about: { file: "about.ts", exports: ["about"] },
    "site-info": { file: "site-info.ts", exports: ["siteInfo"] },
    ordering: { file: "ordering.ts", exports: ["ordering"] },
};

function fail(message) {
    console.error(`\nexport failed: ${message}\n`);
    process.exit(1);
}

/** Every `import … from` / `export … from` must be `import type` (checked on the text, before running it). */
function assertTypeImportsOnly(file, source) {
    const statements = source.match(/^\s*(?:import|export)\b[^;]*?\bfrom\s*["'][^"']+["']/gms) ?? [];
    const valueImports = statements.filter((statement) => !/^\s*import\s+type\b/.test(statement));
    const dynamic = /\bimport\s*\(|\brequire\s*\(/.test(source);

    if (valueImports.length > 0 || dynamic) {
        fail(`${file} imports a real value, not only types:\n  ${(valueImports[0] ?? "import()/require()").trim()}\n` +
            "The mock data must stay plain data (type imports only), so this script never runs frontend code.");
    }
}

/** Branch staff phones are placeholders that repeat the restaurant's public numbers; anything else is a real staff number. */
function assertNoPrivateStaffNumbers(siteInfo) {
    const publicNumbers = new Set(siteInfo.phones.filter((phone) => !phone.branchId).map((phone) => phone.number));
    const privateNumber = siteInfo.phones.find((phone) => phone.branchId && !publicNumbers.has(phone.number));

    if (privateNumber) {
        fail(`site-info: the staff phone "${privateNumber.id}" (branch ${privateNumber.branchId}) isn't one of the restaurant's public numbers.\n` +
            "Real branch staff numbers must never be committed: the owner types them in the dashboard.");
    }
}

/** Every "/assets/…" string in the data. */
function imagePaths(data) {
    const found = new Set();
    JSON.stringify(data, (key, value) => {
        if (typeof value === "string" && value.startsWith("/assets/")) found.add(value);
        return value;
    });
    return [...found].sort();
}

async function filesUnder(dir) {
    const entries = await readdir(dir, { withFileTypes: true, recursive: true }).catch(() => []);
    return entries.filter((entry) => entry.isFile()).map((entry) => path.join(entry.parentPath ?? entry.path, entry.name));
}

const exported = {};

for (const [name, { file, exports }] of Object.entries(SOURCES)) {
    const fullPath = path.join(mockDir, file);
    const source = await readFile(fullPath, "utf8").catch(() => fail(`${fullPath} not found (pass the frontend repo path as the first argument).`));
    assertTypeImportsOnly(file, source);

    const module = await import(pathToFileURL(fullPath).href);
    const missing = exports.filter((key) => !(key in module));
    if (missing.length > 0) fail(`${file} no longer exports ${missing.join(", ")}.`);

    exported[name] = exports.length === 1 ? module[exports[0]] : Object.fromEntries(exports.map((key) => [key, module[key]]));
}

assertNoPrivateStaffNumbers(exported["site-info"]);

for (const [name, value] of Object.entries(exported)) {
    await writeFile(path.join(here, `${name}.json`), `${JSON.stringify(value, null, 4)}\n`, "utf8");
}

// The photos the data uses, at the same relative path as in the frontend (/assets/x → images/x).
const used = imagePaths(exported);
const kept = new Set();
for (const assetPath of used) {
    const source = path.join(frontend, "public", assetPath);
    const target = path.join(imagesDir, assetPath.replace(/^\/assets\//, ""));
    await mkdir(path.dirname(target), { recursive: true });
    await copyFile(source, target).catch(() => fail(`the photo ${assetPath} is used by the data but doesn't exist in the frontend's public/.`));
    kept.add(path.normalize(target));
}
for (const file of await filesUnder(imagesDir)) {
    if (!kept.has(path.normalize(file))) await rm(file);
}

// Which frontend commit the data came from (no timestamp, so an unchanged export gives no diff).
let frontendCommit = null;
try {
    frontendCommit = execFileSync("git", ["-C", frontend, "rev-parse", "HEAD"], { encoding: "utf8" }).trim();
} catch {
    // Not a git checkout: the data is still exported.
}
await writeFile(path.join(here, "source.json"), `${JSON.stringify({ frontendCommit, mockFiles: Object.values(SOURCES).map((s) => `src/data/mock/${s.file}`) }, null, 4)}\n`, "utf8");

console.log(`Exported ${Object.keys(exported).length} JSON files and ${used.length} photos from ${frontend}` +
    (frontendCommit ? ` (commit ${frontendCommit.slice(0, 7)})` : "") + ".\nReview the diff, then commit.");
