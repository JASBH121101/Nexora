(() => {
  const demos = window.NEXORA_DEMOS || [];
  const $ = (selector) => document.querySelector(selector);
  const $ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const make = (tag, text) => {
    const element = document.createElement(tag);
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
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
  const businessSelect = $("#admin-business-select");
  const productRows = $("#admin-product-rows");
  const dialog = $("#product-form-dialog");
  const form = $("#product-form");
  const toast = $("#admin-toast");
  const dynamicFields = $("#dynamic-product-fields");
  const photoField = $("#product-image-field");
  const STUDIO_KEY = "nexora-studio:v1";
  const readStudioConfig = () => {
    try {
      const value = JSON.parse(localStorage.getItem(STUDIO_KEY) || "null");
      return value && value.schemaVersion === 1 && value.catalog ? value : null;
    } catch { return null; }
  };
  const studioConfig = readStudioConfig();
  const photoUploadsEnabled = () => studioConfig ? Boolean(studioConfig.catalog.photosEnabled) : true;
  const schemaGroups = () => studioConfig?.catalog?.groups || [];
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

    const rows = products.map((product) => {
      const row = make("tr");
      const productCell = make("td");
      const productWrap = make("div");
      productWrap.classList.add("table-product");
      const image = make("img");
      image.src = safeImageSource(product.image);
      image.alt = "";
      image.loading = "lazy";
      image.addEventListener("error", () => { image.src = "assets/img/product-placeholder.svg"; }, { once: true });
      const productText = make("span");
      productText.append(make("strong", product.name), make("small", product.detail || "Sin detalle"));
      productWrap.append(image, productText);
      productCell.append(productWrap);

      const categoryCell = make("td", product.category);
      const priceCell = make("td");
      priceCell.append(make("strong", NexoraStore.money(product.price)));
      const stateCell = make("td");
      const state = make("span", "Publicado");
      state.classList.add("state-pill");
      stateCell.append(state);

      const actionsCell = make("td");
      const actions = make("div");
      actions.classList.add("row-actions");
      const edit = make("button", "✎");
      edit.classList.add("row-action");
      edit.type = "button";
      edit.dataset.edit = product.id;
      edit.setAttribute("aria-label", "Editar " + product.name);
      const remove = make("button", "×");
      remove.classList.add("row-action", "is-danger");
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
  const renderDynamicFields = (values = {}) => {
    dynamicFields.replaceChildren();
    const groups = schemaGroups().filter((item) => Array.isArray(item.fields) && item.fields.length);
    if (!groups.length) {
      const note = make("p", "No hay campos personalizados. Puedes configurarlos desde «Configurar negocio».");
      note.className = "dynamic-empty";
      dynamicFields.append(note);
      return;
    }
    groups.forEach((group) => {
      const section = make("section");
      section.className = "dynamic-product-group";
      section.append(make("h3", group.name));
      group.fields.forEach((field) => {
        const label = make("label");
        if (field.width === "full" || field.type === "textarea") label.classList.add("is-full");
        label.append(document.createTextNode(field.label + (field.required ? " *" : "")));
        let control;
        const saved = values[field.id] ?? "";
        if (field.type === "textarea") {
          control = document.createElement("textarea");
          control.value = Array.isArray(saved) ? saved.join(", ") : String(saved);
        } else if (field.type === "select" || field.type === "multiselect") {
          control = document.createElement("select");
          if (field.type === "multiselect") { control.multiple = true; control.size = Math.min(4, Math.max(2, (field.options || []).length)); }
          if (field.type === "select") {
            const empty = make("option", "Seleccionar…");
            empty.value = "";
            control.append(empty);
          }
          (field.options || []).forEach((option) => {
            const item = make("option", option);
            item.value = option;
            item.selected = Array.isArray(saved) ? saved.includes(option) : saved === option;
            control.append(item);
          });
        } else if (field.type === "boolean") {
          control = document.createElement("input");
          control.type = "checkbox";
          control.checked = saved === true || saved === "true";
        } else {
          control = document.createElement("input");
          control.type = field.type === "number" ? "number" : field.type === "date" ? "date" : "text";
          if (field.type === "number") control.step = "any";
          control.value = Array.isArray(saved) ? saved.join(", ") : String(saved);
        }
        control.dataset.customField = field.id;
        control.dataset.customType = field.type;
        if (field.required) control.required = true;
        label.append(control);
        if (field.type === "number" && field.unit) {
          const unit = make("small", field.unit);
          unit.className = "dynamic-field-unit";
          label.append(unit);
        }
        section.append(label);
      });
      dynamicFields.append(section);
    });
  };
  const collectDynamicValues = () => {
    const attributes = {};
    for (const control of $("[data-custom-field]", dynamicFields)) {
      const key = control.dataset.customField;
      if (control.dataset.customType === "boolean") attributes[key] = control.checked;
      else if (control.dataset.customType === "multiselect") attributes[key] = [...control.selectedOptions].map((option) => option.value);
      else if (control.dataset.customType === "number") attributes[key] = control.value === "" ? null : Number(control.value);
      else attributes[key] = control.value.trim();
    }
    return attributes;
  };
  const setPhotoFieldVisibility = () => {
    photoField.hidden = !photoUploadsEnabled();
    $("#product-image").required = false;
    if (!photoUploadsEnabled()) $("#product-image").value = "";
  };
  const resetProductForm = () => {
    form.reset();
    $("#product-id").value = "";
    $("#product-form-title").textContent = "Nuevo producto";
    renderDynamicFields();
    setPhotoFieldVisibility();
  };
  const openCreate = () => {
    resetProductForm();
    $("#product-category").value = business.categories[0] || "";
    $("#product-image").value = photoUploadsEnabled() ? (business.products[0]?.image || "") : "";
    dialog.showModal();
  };
  const openEdit = (id) => {
    const product = products.find((item) => item.id === id);
    if (!product) return;
    resetProductForm();
    $("#product-form-title").textContent = "Editar producto";
    $("#product-id").value = product.id;
    $("#product-name").value = product.name;
    $("#product-category").value = product.category;
    $("#product-price").value = product.price;
    $("#product-old-price").value = product.oldPrice || "";
    $("#product-description").value = product.description;
    $("#product-image").value = product.image || "";
    $("#product-featured").checked = Boolean(product.featured);
    renderDynamicFields(product.attributes || {});
    setPhotoFieldVisibility();
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
    const attributes = collectDynamicValues();
    const product = {
      id,
      name: $("#product-name").value.trim(),
      category: $("#product-category").value.trim(),
      price: Number($("#product-price").value),
      oldPrice: $("#product-old-price").value ? Number($("#product-old-price").value) : null,
      description: $("#product-description").value.trim(),
      image: photoUploadsEnabled() ? $("#product-image").value.trim() : "",
      detail: Object.entries(attributes).filter(([,value]) => value !== "" && value !== null && value !== false && !(Array.isArray(value) && !value.length)).map(([key,value]) => {
        const definition = schemaGroups().flatMap((item) => item.fields || []).find((field) => field.id === key);
        return definition ? definition.label + ": " + (Array.isArray(value) ? value.join(", ") : String(value)) : "";
      }).filter(Boolean).join(" · ").slice(0,180),
      attributes,
      tag: "Demo",
      featured: $("#product-featured").checked
    };
    if (!product.name || !product.category || !Number.isFinite(product.price) || product.price < 0 || !product.description) {
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