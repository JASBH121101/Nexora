import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const read = (path) => readFileSync(new URL(path, import.meta.url), "utf8");
const html = read("../setup.html");
const js = read("../assets/js/setup.js");
const css = read("../assets/css/setup.css");
const scope = read("../../../../docs/PRODUCT-SCOPE.md");

test("setup wizard exposes the four guided stages", () => {
  for (const step of [1, 2, 3, 4]) {
    assert.ok(html.includes('data-step="' + step + '"'), "missing setup step " + step);
  }
  assert.ok(html.includes('id="previous-step"'));
  assert.ok(html.includes('id="next-step"'));
  assert.ok(html.includes('id="save-setup"'));
});

test("the owner may register at most five WhatsApp numbers", () => {
  assert.ok(html.includes('id="phone-list"'));
  assert.ok(js.includes("state.phones.length>=5"));
  assert.ok(js.includes(".slice(0,5)"));
  assert.ok(js.includes("isMain") && js.includes("showPublic"));
});

test("industry templates cover the planned sectors", () => {
  for (const key of ["perfumery", "fashion", "technology", "gym", "automotive", "grocery", "other"]) {
    assert.ok(js.includes(key + ":{"), "missing template " + key);
  }
});

test("custom field definitions support groups, order, types and public visibility", () => {
  for (const token of ["required", "showPublic", "searchable", "width", "unitGroup", "options"]) {
    assert.ok(js.includes(token), "missing field property " + token);
  }
  for (const token of ["text", "number", "select", "multiselect", "boolean", "date", "textarea"]) {
    assert.ok(js.includes(token + ":"), "missing field type " + token);
  }
  assert.ok(js.includes("data-field-move"));
  assert.ok(js.includes("data-group-move"));
});

test("measurement units are separated by dimension", () => {
  for (const dimension of ["mass", "volume", "length", "count", "area"]) {
    assert.ok(js.includes(dimension + ":["), "missing unit dimension " + dimension);
  }
  assert.ok(js.includes("unitsFor(f.unitGroup)"));
});

test("photographs are optional and appear in the preview only when enabled", () => {
  assert.ok(html.includes('id="photos-enabled"'));
  assert.ok(js.includes("photosEnabled:false"));
  assert.ok(js.includes("if(state.photosEnabled)"));
});

test("the demo does not collect a real password or claim production authentication", () => {
  assert.doesNotMatch(html, /type=["']password["']/i);
  assert.match(html, /no crea cuentas ni solicita contraseñas reales/i);
  assert.match(js, /mode:"demo-local-only"/);
  assert.match(js, /localStorage\.setItem\(STORE_KEY/);
});

test("the dynamic product preview has responsive layout rules", () => {
  assert.match(css, /\.product-preview\s*\{[^}]*grid-template-columns:\s*repeat\(2/);
  assert.match(css, /@media\(max-width:700px\)/);
  assert.match(css, /\.product-preview\s*\{[^}]*grid-template-columns:1fr/s);
});

test("the product scope keeps dynamic definitions in application data rather than dynamic SQL", () => {
  assert.match(scope, /No crear tablas SQL arbitrarias/i);
  assert.match(scope, /product_field_definitions/);
  assert.match(scope, /product_field_values/);
});
