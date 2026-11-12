import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const css = readFileSync(new URL("../assets/css/admin.css", import.meta.url), "utf8");

function rule(selector) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = css.match(new RegExp(escaped + "\\s*\\{([^}]*)\\}", "s"));
  return match?.[1] ?? "";
}

test("admin layout containers can shrink instead of forcing page overflow", () => {
  for (const selector of [".admin-layout", ".admin-main", ".admin-page-heading", ".admin-panel", ".table-wrap"]) {
    assert.match(rule(selector), /min-width\\s*:\\s*0/, selector + " must allow shrinking");
  }
});

test("the products table scrolls inside its own wrapper on narrow screens", () => {
  assert.match(rule(".table-wrap"), /overflow-x\\s*:\\s*auto/);
  assert.match(rule(".table-wrap"), /max-width\\s*:\\s*100%/);
  assert.match(rule(".product-table"), /min-width\\s*:\\s*590px/);
});

test("admin mobile breakpoint keeps main content constrained to the viewport", () => {
  assert.match(css, /@media\\s*\\(max-width:\\s*760px\\)[\\s\\S]*?\\.admin-main\\s*\\{[^}]*max-width:\\s*100%/);
});
