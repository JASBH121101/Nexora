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
  const render = () => {
    const categories = new Set(products.map((item) => item.category).filter(Boolean));
    $("#stat-products").textContent = String(products.length);
    $("#stat-categories").textContent = String(categories.size);
    $("#stat-featured").textContent = String(products.filter((item) => item.featured).length);
    $("#admin-empty").hidden = products.length > 0;
    productRows.innerHTML = products.map((product) => (
      "<tr>" +
      '<td><div class="table-product"><img src="' + NexoraStore.escapeHTML(product.image) + '" alt="" loading="lazy">' +
      "<span><strong>" + NexoraStore.escapeHTML(product.name) + "</strong><small>" + NexoraStore.escapeHTML(product.detail || "Sin detalle") + "</small></span></div></td>" +
      "<td>" + NexoraStore.escapeHTML(product.category) + "</td>" +
      "<td><strong>" + NexoraStore.money(product.price) + "</strong></td>" +
      '<td><span class="state-pill">Publicado</span></td>' +
      '<td><div class="row-actions"><button class="row-action" type="button" data-edit="' +
      NexoraStore.escapeHTML(product.id) + '" aria-label="Editar ' + NexoraStore.escapeHTML(product.name) + '">✎</button>' +
      '<button class="row-action is-danger" type="button" data-delete="' +
      NexoraStore.escapeHTML(product.id) + '" aria-label="Eliminar ' + NexoraStore.escapeHTML(product.name) + '">×</button></div></td>' +
      "</tr>"
    )).join("");
    productRows.querySelectorAll("img").forEach((image) => image.addEventListener("error", () => {
      image.src = "assets/img/product-placeholder.svg";
    }, { once: true }));
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