import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";

test("teacher styles do not apply caption spacing to the brand or auto margins to task status", () => {
  const css = readFileSync(new URL("../app/globals.css", import.meta.url), "utf8");
  assert.ok(!css.includes(".teacher-header .student-brand span"), "BrandMark is a span too: use an explicit caption class");
  assert.match(css, /\.student-detail-grid \.status-check\s*\{[^}]*margin:\s*0/);
  assert.ok(!css.includes(".teacher-module-detail p { display: inline-flex; width: calc(33.333% - 8px)"), "Task rows must not be squeezed into thirds");
});

test("knowledge base covers the curriculum and searches article content without case sensitivity", async () => {
  const { articles, searchArticles } = await import("./knowledge.ts");
  assert.ok(articles.length >= 14);
  assert.equal(new Set(articles.map((article) => article.id)).size, articles.length);
  for (const topic of ["HTTP", "JSON", "XSS", "SQL", "IDOR", "JWT", "SSRF", "MFA"]) {
    assert.ok(searchArticles(topic).length > 0, topic);
  }
  assert.deepEqual(searchArticles("  xSs  "), searchArticles("XSS"));
  assert.equal(searchArticles("несуществующийтермин").length, 0);
  assert.equal(searchArticles("").length, articles.length);
  assert.equal(searchArticles("", "Инъекции").length, 3);
  assert.equal(searchArticles("XSS", "Вход и сессии").length, 0);
  assert.ok(searchArticles("XSS контекст").every((article) => article.category === "Инъекции"));
  assert.ok(searchArticles("атомарность").some((article) => article.id === "race"));
  for (const article of articles) {
    assert.ok(article.sections.length >= 3);
    assert.ok(article.example.code.length > 0);
    assert.ok(article.check.length > 0);
    assert.ok(!JSON.stringify(article).includes("/api/store/"), "No walkthrough endpoints");
  }
});
