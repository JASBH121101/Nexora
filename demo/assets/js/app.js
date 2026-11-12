(() => {
  const demos = window.NEXORA_DEMOS || [];
  const $ = (selector) => document.querySelector(selector);
  const businessSelect = $("#business-select");
  const categoryList = $("#category-list");
  const productGrid = $("#product-grid");
  const searchInput = $("#product-search");
  const emptyState = $("#empty-state");
  const toast = $("#toast");
  const dialog = $("#product-dialog");
  let business = demos.find((item) => item.id === businessSelect?.value) || demos[0];
  let settings = business ? NexoraStore.getSettings(business) : {};
  let products = business ? NexoraStore.getProducts(business) : [];
  let activeCategory = "all";
  let searchTerm = "";
  let toastTimer;

  if (!business) return;

  const showToast = (message) => {
    toast.textContent = message;
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 2600);
  };

  const buildWhatsApp = (message) => NexoraStore.whatsappURL(settings.whatsapp, message);
  const setWhatsAppLink = (element, message) => {
    const url = buildWhatsApp(message);
    element.href = url || "#";
    element.setAttribute("aria-disabled", String(!url));
    element.onclick = url ? null : (event) => {
      event.preventDefault();
      showToast("Configura un número de WhatsApp válido en el panel de demostración.");
    };
  };

  const renderCategories = () => {
    const labels = ["Todos", ...business.categories];
    categoryList.innerHTML = labels.map((label, index) => {
      const value = index === 0 ? "all" : label;
      const active = activeCategory === value ? " is-active" : "";
      return '<button class="category-chip' + active + '" type="button" data-category="' +
        NexoraStore.escapeHTML(value) + '">' + NexoraStore.escapeHTML(label) + "</button>";
    }).join("");
  };

  const productCard = (product) => {
    const tag = product.tag ? '<span class="product-tag">' + NexoraStore.escapeHTML(product.tag) + "</span>" : "";
    const oldPrice = Number(product.oldPrice) > Number(product.price)
      ? '<del class="old-price">' + NexoraStore.money(product.oldPrice) + "</del>" : "";
    return '<article class="product-card">' +
      '<div class="product-image">' +
        '<img src="' + NexoraStore.escapeHTML(product.image) + '" alt="' + NexoraStore.escapeHTML(product.name) + '" loading="lazy">' +
        tag +
        '<button class="product-quick" type="button" data-product-id="' + NexoraStore.escapeHTML(product.id) +
          '" aria-label="Ver detalles de ' + NexoraStore.escapeHTML(product.name) + '">↗</button>' +
      "</div>" +
      '<div class="product-info">' +
        '<span class="product-category">' + NexoraStore.escapeHTML(product.category) + "</span>" +
        "<h3>" + NexoraStore.escapeHTML(product.name) + "</h3>" +
        '<p class="product-detail">' + NexoraStore.escapeHTML(product.detail || "Consulta más información") + "</p>" +
        '<div class="price-row"><span class="product-price">' + NexoraStore.money(product.price) + "</span>" + oldPrice + "</div>" +
      "</div>" +
    "</article>";
  };

  const filteredProducts = () => products.filter((product) => {
    const categoryMatch = activeCategory === "all" || product.category === activeCategory;
    const text = [product.name, product.category, product.description, product.detail].join(" ").toLowerCase();
    return categoryMatch && text.includes(searchTerm);
  });

  const renderProducts = () => {
    const matches = filteredProducts();
    productGrid.innerHTML = matches.map(productCard).join("");
    emptyState.hidden = matches.length > 0;
    $("#results-count").textContent = matches.length + (matches.length === 1 ? " producto" : " productos");
    productGrid.querySelectorAll("img").forEach((image) => {
      image.addEventListener("error", () => {
        image.src = "assets/img/product-placeholder.svg";
      }, { once: true });
    });
  };

  const renderBusiness = () => {
    settings = NexoraStore.getSettings(business);
    products = NexoraStore.getProducts(business);
    activeCategory = "all";
    searchTerm = "";
    searchInput.value = "";
    $("#business-kicker").textContent = settings.name.toUpperCase() + " · " + business.tagline;
    $("#hero-title").innerHTML = NexoraStore.escapeHTML(business.headline[0]) +
      "<br><span>" + NexoraStore.escapeHTML(business.headline[1]) + "</span>";
    $("#hero-description").textContent = business.description;
    $("#hero-image").src = business.heroImage;
    $("#hero-image").alt = "Imagen ilustrativa de " + settings.name;
    $("#hero-feature-label").textContent = business.featureLabel;
    $("#hero-total").textContent = String(products.length).padStart(2, "0");
    $("#business-contact-copy").textContent = settings.contact;
    setWhatsAppLink($("#header-whatsapp"), "Hola, me gustaría recibir más información de " + settings.name + ".");
    setWhatsAppLink($("#main-whatsapp"), "Hola, me gustaría recibir más información del catálogo de " + settings.name + ".");
    renderCategories();
    renderProducts();
  };

  const openProduct = (id) => {
    const product = products.find((item) => item.id === id);
    if (!product) return;
    $("#dialog-image").src = product.image;
    $("#dialog-image").alt = product.name;
    $("#dialog-image").onerror = () => { $("#dialog-image").src = "assets/img/product-placeholder.svg"; };
    $("#dialog-category").textContent = product.category;
    $("#dialog-title").textContent = product.name;
    $("#dialog-description").textContent = product.description || "Consulta con el negocio para conocer más detalles.";
    $("#dialog-attributes").innerHTML = product.detail
      ? '<span class="attribute-pill">' + NexoraStore.escapeHTML(product.detail) + "</span>" : "";
    $("#dialog-price").innerHTML = NexoraStore.money(product.price) +
      (Number(product.oldPrice) > Number(product.price) ? " <del>" + NexoraStore.money(product.oldPrice) + "</del>" : "");
    setWhatsAppLink($("#dialog-whatsapp"), "Hola, me interesa " + product.name + " (" + NexoraStore.money(product.price) + "). ¿Me pueden brindar más información?");
    dialog.showModal();
  };

  businessSelect.addEventListener("change", () => {
    business = demos.find((item) => item.id === businessSelect.value) || demos[0];
    renderBusiness();
  });
  categoryList.addEventListener("click", (event) => {
    const button = event.target.closest("[data-category]");
    if (!button) return;
    activeCategory = button.dataset.category;
    renderCategories();
    renderProducts();
  });
  productGrid.addEventListener("click", (event) => {
    const button = event.target.closest("[data-product-id]");
    if (button) openProduct(button.dataset.productId);
  });
  searchInput.addEventListener("input", () => {
    searchTerm = searchInput.value.trim().toLocaleLowerCase("es");
    renderProducts();
  });
  $("#clear-filters").addEventListener("click", () => {
    activeCategory = "all";
    searchTerm = "";
    searchInput.value = "";
    renderCategories();
    renderProducts();
  });
  $("#dialog-close").addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "/" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
      event.preventDefault();
      searchInput.focus();
    }
  });
  $("#current-year").textContent = String(new Date().getFullYear());
  $("#hero-image").addEventListener("error", () => {
    $("#hero-image").src = "assets/img/product-placeholder.svg";
  }, { once: true });
  renderBusiness();
})();