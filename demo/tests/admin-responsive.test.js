import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const css = readFileSync(new URL("../assets/css/admin.css", import.meta.url), "utf8");

function declarations(selector) {
  return [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
    .filter(([, selectors]) => selectors.split(",").some((item) => item.trim() === selector))
    .map(([, , body]) => body)
    .join("\n");
}

test("admin containers can shrink instead of forcing page overflow", () => {
  for (const selector of [".admin-layout", ".admin-sidebar", ".admin-main", ".admin-page-heading", ".admin-panel", ".table-wrap"]) {
    assert.match(declarations(selector), /min-width\s*:\s*0/, selector + " must allow shrinking");
  }
});

test("the products table scrolls inside its own wrapper on narrow screens", () => {
  assert.match(declarations(".table-wrap"), /max-width\s*:\s*100%/);
  assert.match(declarations(".table-wrap"), /overflow-x\s*:\s*auto/);
  assert.match(declarations(".product-table"), /min-width\s*:\s*590px/);
});

test("admin mobile breakpoint keeps main content constrained to the viewport", () => {
  assert.match(declarations(".admin-main"), /max-width\s*:\s*100%/);
});
