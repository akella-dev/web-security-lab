import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";

test("teacher key starts empty and is not shipped as a demo credential", () => {
  const source = readFileSync(new URL("../components/TeacherDashboard.tsx", import.meta.url), "utf8");
  assert.ok(!source.includes("teacher-demo-key"));
  assert.match(source, /\[key, setKey\] = useState\(""\)/);
});

test("favicon contains the same paths as the existing brand", () => {
  const brand = readFileSync(new URL("../components/BrandMark.tsx", import.meta.url), "utf8");
  const icon = readFileSync(new URL("../app/icon.svg", import.meta.url), "utf8");
  for (const match of brand.matchAll(/<path d="([^"]+)"/g)) assert.ok(icon.includes(match[1]));
});

test("student address comes from the browser without server network configuration", () => {
  const source = readFileSync(new URL("../components/TeacherDashboard.tsx", import.meta.url), "utf8");
  assert.ok(source.includes("window.location.origin"));
  const page = readFileSync(new URL("../app/teacher/page.tsx", import.meta.url), "utf8");
  assert.ok(!page.includes("process.env.STUDENT_URL"));
});
