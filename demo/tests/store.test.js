import test from "node:test";
import assert from "node:assert/strict";

const values = new Map();
globalThis.window = globalThis;
globalThis.localStorage = {
  getItem(key) { return values.has(key) ? values.get(key) : null; },
  setItem(key, value) { values.set(key, String(value)); }
};
await import("../assets/js/store.js");
const store = globalThis.NexoraStore;

test("HTML escaping encodes markup-sensitive characters", () => {
  assert.equal(store.escapeHTML(`<script a="b">&'x'</script>`), "&lt;script a=&quot;b&quot;&gt;&amp;&#39;x&#39;&lt;/script&gt;");
});

test("product data is copied and can be stored by business", () => {
  const business = { id: "test-business", products: [{ id: "p1", name: "Sample" }] };
  const products = store.getProducts(business);
  products.push({ id: "p2", name: "Second" });
  assert.equal(store.getProducts(business).length, 1);
  assert.equal(store.saveProducts(business.id, products), true);
  assert.equal(store.getProducts(business).length, 2);
});

test("WhatsApp links normalize numbers and encode message text", () => {
  const link = store.whatsappURL("+505 0000-0000", "Hola NEXORA & demo");
  assert.ok(link.startsWith("https://wa.me/50500000000?text="));
  assert.ok(link.includes("Hola%20NEXORA%20%26%20demo"));
  assert.equal(store.whatsappURL("123", "Hola"), "");
});


test("malformed but valid JSON product records fall back to a cloned demo catalogue", () => {
  const business = {
    id: "shape-validation",
    products: [{ id: "seed", name: "Seed product" }]
  };
  const invalidRecords = [null, { products: "not-an-array" }, 42, "unexpected"];

  for (const invalidRecord of invalidRecords) {
    values.set("nexora-demo-v1:products:" + business.id, JSON.stringify(invalidRecord));
    const products = store.getProducts(business);

    assert.deepEqual(products, business.products);
    assert.notStrictEqual(products, business.products);
    products.push({ id: "local-mutation", name: "Mutation" });
    assert.deepEqual(store.getProducts(business), business.products);
  }
});

test("product persistence rejects non-array payloads without replacing valid data", () => {
  const business = {
    id: "save-shape-validation",
    products: [{ id: "stable", name: "Stable product" }]
  };
  const invalidPayloads = [null, {}, "not-an-array", 7];

  assert.equal(store.saveProducts(business.id, business.products), true);
  const before = JSON.stringify(store.getProducts(business));

  for (const invalidPayload of invalidPayloads) {
    assert.equal(store.saveProducts(business.id, invalidPayload), false);
    assert.equal(JSON.stringify(store.getProducts(business)), before);
  }
});

test("settings storage ignores array-shaped records and rejects invalid writes", () => {
  const business = {
    id: "settings-shape-validation",
    name: "Demo business",
    whatsapp: "50500000000",
    contact: "Default contact"
  };
  values.set(
    "nexora-demo-v1:settings:" + business.id,
    JSON.stringify(["unexpected", "array"])
  );

  assert.deepEqual(store.getSettings(business), {
    name: business.name,
    whatsapp: business.whatsapp,
    contact: business.contact
  });
  assert.equal(store.saveSettings(business.id, null), false);
  assert.equal(store.saveSettings(business.id, []), false);
  assert.equal(store.saveSettings(business.id, "not-an-object"), false);
});

test("business catalogues remain isolated through bounded repeated storage round trips", () => {
  const firstBusiness = {
    id: "stress-business-a",
    products: [{ id: "a-seed", name: "A seed" }]
  };
  const secondBusiness = {
    id: "stress-business-b",
    products: [{ id: "b-seed", name: "B seed" }]
  };

  for (let round = 0; round < 24; round += 1) {
    const firstProducts = store.getProducts(firstBusiness);
    firstProducts.push({
      id: "a-" + round,
      name: "Product A " + round,
      description: "bounded fixture ".repeat(20)
    });

    assert.equal(store.saveProducts(firstBusiness.id, firstProducts), true);

    const secondProducts = store.getProducts(secondBusiness);
    assert.equal(secondProducts.length, 1);
    assert.equal(secondProducts[0].id, "b-seed");

    const detached = store.getProducts(firstBusiness);
    detached[0].name = "unsaved mutation";
    assert.notEqual(store.getProducts(firstBusiness)[0].name, "unsaved mutation");
  }

  assert.equal(store.getProducts(firstBusiness).length, 25);
  assert.equal(store.getProducts(secondBusiness).length, 1);
  assert.equal(store.getProducts(secondBusiness)[0].id, "b-seed");
});
