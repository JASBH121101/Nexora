(() => {
  const demos = window.NEXORA_DEMOS || [];
  const $ = (selector) => document.querySelector(selector);
  const businessSelect = $("#admin-business-select");
  const productRows = $("#admin-product-rows");
  const dialog = $("#product-form-dialog");
  const form = $("#product-form");
  const toast = $("#admin-toast");
  let business = demos.find((item) => item.id === businessSelect?.value) || demos[0];
  let products = business ? NexoraStore.getProducts(business) : [];
  let toastTimer;

  if (!business) return;

  const showToast = (message) => {
    toast.textContent = message;
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 2600);
  };
  const persistProducts = () => {
    if (!NexoraStore.saveProducts(business.id, products)) {
      showToast("No fue posible guardar. El almacenamiento local puede estar lleno.");
      return false;
    }
    render();
    return true;
  };
  const safeImageSource = (value) => {
    try {
      const url = new URL(String(value), window.location.href);
      if (url.protocol === "https:" || url.protocol === "http:") return url.href;
    } catch {
      // Invalid image URLs use the local fallback below.
    }
    return "assets/img/product-placeholder.svg";
  };
  const make = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
  };
  const render = () => {
    const categories = new Set(products.map((item) => item.category).filter(Boolean));
    $("#stat-products").textContent = String(products.length);
    $("#stat-categories").textContent = String(categories.size);
    $("#stat-featured").textContent = String(products.filter((item) => item.featured).length);
    $("#admin-empty").hidden = products.length > 0;

    const rows = products.map((product) => {
      const row = make("tr");
      const productCell = make("td");
      const productWrap = make("div", "table-product");
      const image = make("img");
      image.src = safeImageSource(product.image);
      image.alt = "";
      image.loading = "lazy";
      image.addEventListener("error", () => { image.src = "assets/img/product-placeholder.svg"; }, { once: true });
      const productText = make("span");
      productText.append(make("strong", "", product.name), make("small", "", product.detail || "Sin detalle"));
      productWrap.append(image, productText);
      productCell.append(productWrap);

      const categoryCell = make("td", "", product.category);
      const priceCell = make("td");
      priceCell.append(make("strong", "", NexoraStore.money(product.price)));
      const stateCell = make("td");
      stateCell.append(make("span", "state-pill", "Publicado"));

      const actionsCell = make("td");
      const actions = make("div", "row-actions");
      const edit = make("button", "row-action", "✎");
      edit.type = "button";
      edit.dataset.edit = product.id;
      edit.setAttribute("aria-label", "Editar " + product.name);
      const remove = make("button", "row-action is-danger", "×");
      remove.type = "button";
      remove.dataset.delete = product.id;
      remove.setAttribute("aria-label", "Eliminar " + product.name);
      actions.append(edit, remove);
      actionsCell.append(actions);
      row.append(productCell, categoryCell, priceCell, stateCell, actionsCell);
      return row;
    });
    productRows.replaceChildren(...rows);
  };
  const loadSettings = () => {
    const settings = NexoraStore.getSettings(business);
    $("#setting-name").value = settings.name;
    $("#setting-whatsapp").value = settings.whatsapp;
    $("#setting-contact").value = settings.contact;
  };
  const resetProductForm = () => {
    form.reset();
    $("#product-id").value = "";
    $("#product-form-title").textContent = "Nuevo producto";
  };
  const openCreate = () => {
    resetProductForm();
    $("#product-category").value = business.categories[0] || "";
    $("#product-image").value = business.products[0]?.image || "";
    dialog.showModal();
  };
  const openEdit = (id) => {
    const product = products.find((item) => item.id === id);
    if (!product) return;
    $("#product-form-title").textContent = "Editar producto";
    $("#product-id").value = product.id;
    $("#product-name").value = product.name;
    $("#product-category").value = product.category;
    $("#product-price").value = product.price;
    $("#product-old-price").value = product.oldPrice || "";
    $("#product-description").value = product.description;
    $("#product-image").value = product.image;
    $("#product-detail").value = product.detail || "";
    $("#product-featured").checked = Boolean(product.featured);
    dialog.showModal();
  };

  businessSelect.addEventListener("change", () => {
    business = demos.find((item) => item.id === businessSelect.value) || demos[0];
    products = NexoraStore.getProducts(business);
    render();
    loadSettings();
    showToast("Catálogo cambiado a " + business.name + ".");
  });
  $("#new-product-button").addEventListener("click", openCreate);
  $("#product-form-close").addEventListener("click", () => dialog.close());
  $("#cancel-product").addEventListener("click", () => dialog.close());
  productRows.addEventListener("click", (event) => {
    const editButton = event.target.closest("[data-edit]");
    const deleteButton = event.target.closest("[data-delete]");
    if (editButton) openEdit(editButton.dataset.edit);
    if (deleteButton) {
      const selected = products.find((item) => item.id === deleteButton.dataset.delete);
      if (!selected || !window.confirm("¿Eliminar " + selected.name + " de esta demostración?")) return;
      products = products.filter((item) => item.id !== selected.id);
      if (persistProducts()) showToast("Producto eliminado de la demo.");
    }
  });
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const id = $("#product-id").value || ("demo-" + Date.now().toString(36));
    const product = {
      id,
      name: $("#product-name").value.trim(),
      category: $("#product-category").value.trim(),
      price: Number($("#product-price").value),
      oldPrice: $("#product-old-price").value ? Number($("#product-old-price").value) : null,
      description: $("#product-description").value.trim(),
      image: $("#product-image").value.trim(),
      detail: $("#product-detail").value.trim(),
      tag: "Demo",
      featured: $("#product-featured").checked
    };
    if (!product.name || !product.category || !Number.isFinite(product.price) || product.price < 0 || !product.description || !product.image) {
      showToast("Revisa los campos obligatorios.");
      return;
    }
    if (product.oldPrice !== null && (!Number.isFinite(product.oldPrice) || product.oldPrice < product.price)) {
      showToast("El precio anterior debe ser mayor que el precio actual.");
      return;
    }
    const existing = products.some((item) => item.id === id);
    products = existing ? products.map((item) => item.id === id ? product : item) : [product, ...products];
    if (persistProducts()) {
      dialog.close();
      showToast(existing ? "Producto actualizado en la demo." : "Producto agregado a la demo.");
    }
  });
  $("#settings-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const settings = {
      name: $("#setting-name").value.trim(),
      whatsapp: $("#setting-whatsapp").value.trim(),
      contact: $("#setting-contact").value.trim()
    };
    const digits = settings.whatsapp.replace(/\D/g, "");
    if (settings.name.length < 2 || digits.length < 8 || digits.length > 15) {
      showToast("Revisa el nombre y el número de WhatsApp.");
      return;
    }
    if (!NexoraStore.saveSettings(business.id, settings)) {
      showToast("No fue posible guardar la configuración local.");
      return;
    }
    showToast("Configuración guardada en este navegador.");
  });
  loadSettings();
  render();
})();