document.addEventListener('DOMContentLoaded', function () {
    const qtyInputs = document.querySelectorAll('.qty-input');
    const searchInput = document.getElementById('productSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const sortSelect = document.getElementById('productSortSelect');
    const sortedWrapper = document.getElementById('sortedProductsWrapper');
    const categoryAccordionsWrapper = document.getElementById('categoryAccordionsWrapper');
    const noSearchBanner = document.getElementById('noSearchProductsFound');

    const orderForm = document.getElementById('orderForm');
    const submitBtn = document.getElementById('submitOrderBtn');
    const validationTip = document.getElementById('formValidationTip');
    const phoneInput = document.getElementById('customer_phone');
    const phoneErrorMsg = document.getElementById('phoneErrorMsg');
    const emailInput = document.getElementById('customer_email');
    const emailErrorMsg = document.getElementById('emailErrorMsg');
    const nameInput = document.getElementById('customer_name');
    const cityInput = document.getElementById('customer_city');
    const stateInput = document.getElementById('customer_state');
    const addressInput = document.getElementById('customer_address');
    const pincodeInput = document.getElementById('customer_pincode');
    const saveFields = document.querySelectorAll('.save-field');

    const whatsappBtn = document.getElementById('whatsappChatBtn');
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    const storeWhatsappNumber = "919094925233";

    const summaryItemEl = document.getElementById('summary-item-count');
    const summaryTotalEl = document.getElementById('summary-total-amount');
    const summarySavingsEl = document.getElementById('summary-savings');

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('product-preview-trigger')) {
            const imgSrc = e.target.dataset.fullimg;
            const title = e.target.dataset.title;
            const price = e.target.dataset.price;

            if (typeof Swal !== 'undefined' && imgSrc) {
                Swal.fire({
                    title: `<span class="fs-5 fw-bold">${title}</span>`,
                    html: `<div class="fw-bold text-danger mb-2">Offer Price: ₹${price}</div>`,
                    imageUrl: imgSrc,
                    imageAlt: title,
                    imageHeight: 320,
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0',
                        image: 'rounded-3 object-fit-contain'
                    }
                });
            }
        }
    });

    const canvas = document.getElementById('crackerCanvas');
    const ctx = canvas ? canvas.getContext('2d') : null;
    let w, h, particles = [];

    function resizeCanvas() {
        if (canvas) {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
        }
    }
    window.addEventListener('resize', resizeCanvas, false);
    resizeCanvas();

    function Particle(x, y) {
        this.w = this.h = Math.random() * 4 + 2;
        this.x = x - this.w / 2;
        this.y = y - this.h / 2;
        this.vx = (Math.random() - 0.5) * 12;
        this.vy = (Math.random() - 0.5) * 12;
        this.alpha = 1.0;
        this.color = `hsl(${~~(Math.random() * 360)}, 100%, 60%)`;
    }

    Particle.prototype = {
        gravity: 0.12,
        move: function () {
            this.x += this.vx;
            this.vy += this.gravity;
            this.y += this.vy;
            this.alpha -= 0.018;
            return !(this.x <= 0 || this.x >= w || this.y >= h || this.alpha <= 0);
        },
        draw: function (c) {
            c.save();
            c.beginPath();
            c.translate(this.x + this.w / 2, this.y + this.h / 2);
            c.arc(0, 0, this.w, 0, Math.PI * 2);
            c.fillStyle = this.color;
            c.globalAlpha = this.alpha;
            c.closePath();
            c.fill();
            c.restore();
        }
    };

    function triggerCrackerAt(x, y, count = 70) {
        for (let i = 0; i < count; i++) {
            particles.push(new Particle(x, y));
        }
    }

    function triggerEntryFireworks() {
        let launchCount = 0;
        const interval = setInterval(() => {
            const rx = Math.random() * (w - 200) + 100;
            const ry = Math.random() * (h * 0.6) + 100;
            triggerCrackerAt(rx, ry, 90);
            launchCount++;
            if (launchCount >= 8) {
                clearInterval(interval);
            }
        }, 400);
    }
    triggerEntryFireworks();

    function renderWorld() {
        if (ctx) {
            ctx.clearRect(0, 0, w, h);
            ctx.globalCompositeOperation = 'lighter';
            const alive = [];
            particles.forEach(p => {
                if (p.move()) {
                    p.draw(ctx);
                    alive.push(p);
                }
            });
            particles = alive;
        }
        window.requestAnimationFrame(renderWorld);
    }
    window.requestAnimationFrame(renderWorld);

    let currentAnimValues = { items: 0, total: 0, savings: 0 };

    function animateNumber(element, start, end, isCurrency = false, duration = 400) {
        if (!element) return;
        let startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            const progress = Math.min((timestamp - startTime) / duration, 1);
            const currentVal = start + (end - start) * progress;
            element.textContent = isCurrency ? currentVal.toFixed(2) : Math.round(currentVal);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        }
        window.requestAnimationFrame(step);
    }

    function triggerCardPopAnimation() {
        [
            document.getElementById('card-items-selected'),
            document.getElementById('card-total-amount'),
            document.getElementById('card-savings')
        ].forEach(card => {
            if (!card) return;
            card.classList.remove('pop-pulse');
            void card.offsetWidth;
            card.classList.add('pop-pulse');
            setTimeout(() => card.classList.remove('pop-pulse'), 350);
        });
    }

    let totalItemsCountGlobal = 0;
    let grandTotalGlobal = 0;

    function calculateTotals(triggerAnim = true) {
        let totalItemsCount = 0;
        let grandTotal = 0;
        const currentQuantities = {};

        qtyInputs.forEach(input => {
            const qty = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            const id = input.dataset.id;
            const subtotal = qty * price;
            const subtotalEl = document.getElementById(`subtotal-${id}`);
            if (subtotalEl) {
                subtotalEl.textContent = subtotal.toFixed(2);
            }
            if (qty > 0) {
                totalItemsCount += qty;
                grandTotal += subtotal;
                currentQuantities[id] = qty;
            }
        });

        totalItemsCountGlobal = totalItemsCount;
        grandTotalGlobal = grandTotal;

        try {
            localStorage.setItem('retail_cart_quantities', JSON.stringify(currentQuantities));
        } catch (e) {}

        const newSavings = grandTotal * 0.25;

        if (triggerAnim) {
            animateNumber(summaryItemEl, currentAnimValues.items, totalItemsCount, false);
            animateNumber(summaryTotalEl, currentAnimValues.total, grandTotal, true);
            animateNumber(summarySavingsEl, currentAnimValues.savings, newSavings, true);
            triggerCardPopAnimation();
        } else {
            if (summaryItemEl) summaryItemEl.textContent = totalItemsCount;
            if (summaryTotalEl) summaryTotalEl.textContent = grandTotal.toFixed(2);
            if (summarySavingsEl) summarySavingsEl.textContent = newSavings.toFixed(2);
        }

        currentAnimValues.items = totalItemsCount;
        currentAnimValues.total = grandTotal;
        currentAnimValues.savings = newSavings;

        updateWhatsappLink();
        validateFormState();
    }

    try {
        const savedLocalCart = localStorage.getItem('retail_cart_quantities');
        if (savedLocalCart) {
            const parsed = JSON.parse(savedLocalCart);
            qtyInputs.forEach(input => {
                const pId = input.dataset.id;
                if (parsed[pId] !== undefined) {
                    input.value = parsed[pId];
                }
            });
        }
    } catch (e) {}

    function updateWhatsappLink() {
        if (!whatsappBtn) return;
        let message = 'Hello! I am browsing your Cracker Catalog.';
        if (totalItemsCountGlobal > 0) {
            message += `\n\nI have selected *${totalItemsCountGlobal} items* worth *₹${grandTotalGlobal.toFixed(2)}*. I would like to place an order / get a quotation!`;
        } else {
            message += '\n\nI have a query regarding your products.';
        }
        whatsappBtn.href = `https://wa.me/${storeWhatsappNumber}?text=${encodeURIComponent(message)}`;
    }

    function validatePhoneNumber() {
        if (!phoneInput) return false;
        phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
        const phoneVal = phoneInput.value.trim();
        if (phoneVal === '') {
            if (phoneErrorMsg) phoneErrorMsg.style.display = 'none';
            phoneInput.classList.remove('is-valid-field', 'is-invalid-field');
            return false;
        } else if (!/^[0-9]{10}$/.test(phoneVal)) {
            if (phoneErrorMsg) {
                phoneErrorMsg.style.display = 'block';
                phoneErrorMsg.className = 'form-text text-danger fw-semibold mt-1';
                phoneErrorMsg.textContent = `Please enter a valid 10-digit mobile number (${phoneVal.length}/10 digits).`;
            }
            phoneInput.classList.remove('is-valid-field');
            phoneInput.classList.add('is-invalid-field');
            return false;
        } else {
            if (phoneErrorMsg) {
                phoneErrorMsg.style.display = 'block';
                phoneErrorMsg.className = 'form-text text-success fw-semibold mt-1';
                phoneErrorMsg.textContent = '✓ Valid 10-digit mobile number';
            }
            phoneInput.classList.remove('is-invalid-field');
            phoneInput.classList.add('is-valid-field');
            return true;
        }
    }

    function validateEmailAddress() {
        if (!emailInput) return true;
        const emailVal = emailInput.value.trim();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (emailVal === '') {
            if (emailErrorMsg) emailErrorMsg.style.display = 'none';
            emailInput.classList.remove('is-invalid-field', 'is-valid-field');
            return true;
        }
        if (!emailRegex.test(emailVal)) {
            if (emailErrorMsg) {
                emailErrorMsg.style.display = 'block';
                emailErrorMsg.className = 'form-text text-danger fw-semibold mt-1';
                emailErrorMsg.textContent = 'Please enter a valid e-mail address.';
            }
            emailInput.classList.remove('is-valid-field');
            emailInput.classList.add('is-invalid-field');
            return false;
        } else {
            if (emailErrorMsg) {
                emailErrorMsg.style.display = 'block';
                emailErrorMsg.className = 'form-text text-success fw-semibold mt-1';
                emailErrorMsg.textContent = '✓ Valid e-mail address';
            }
            emailInput.classList.remove('is-invalid-field');
            emailInput.classList.add('is-valid-field');
            return true;
        }
    }

    function validateFormState() {
        if (!submitBtn) return;
        const isPhoneValid = validatePhoneNumber();
        const isEmailValid = validateEmailAddress();
        const isNameValid = nameInput && nameInput.value.trim() !== '';
        const isCityValid = cityInput && cityInput.value.trim() !== '';
        const isStateValid = stateInput && stateInput.value.trim() !== '';
        const isAddressValid = addressInput && addressInput.value.trim() !== '';
        const isPincodeValid = pincodeInput && pincodeInput.value.trim() !== '';
        const hasItemsInCart = totalItemsCountGlobal > 0;
        if (!hasItemsInCart) {
            if (validationTip) validationTip.textContent = '⚠️ Please select at least 1 cracker item in the catalog above.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isNameValid) {
            if (validationTip) validationTip.textContent = '⚠️ Full Name is required.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isPhoneValid) {
            if (validationTip) validationTip.textContent = '⚠️ Valid 10-digit Mobile Number is required.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isEmailValid) {
            if (validationTip) validationTip.textContent = '⚠️ Please enter a valid e-mail address.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isAddressValid || !isCityValid || !isStateValid || !isPincodeValid) {
            if (validationTip) validationTip.textContent = '⚠️ Please complete all address fields (Address, City, State, Pincode).';
            submitBtn.setAttribute('disabled', 'disabled');
        } else {
            if (validationTip) validationTip.textContent = '';
            submitBtn.removeAttribute('disabled');
        }
    }

    function restoreFormFields() {
        let restoredState = false;
        try {
            const savedFormData = localStorage.getItem('retail_customer_form');
            if (savedFormData) {
                const parsedForm = JSON.parse(savedFormData);
                saveFields.forEach(field => {
                    if (parsedForm[field.id] !== undefined && parsedForm[field.id] !== '') {
                        field.value = parsedForm[field.id];
                        if (field.id === 'customer_state') restoredState = true;
                    }
                });
            }
        } catch (e) {}
        if (!restoredState && stateInput) {
            stateInput.value = 'Tamil Nadu';
        }
    }

    function saveFormFields() {
        try {
            const formData = {};
            saveFields.forEach(field => {
                if (field.id) {
                    formData[field.id] = field.value;
                }
            });
            localStorage.setItem('retail_customer_form', JSON.stringify(formData));
        } catch (e) {}
        validateFormState();
    }

    saveFields.forEach(field => {
        field.addEventListener('input', saveFormFields);
        field.addEventListener('change', saveFormFields);
        field.addEventListener('keyup', saveFormFields);
        field.addEventListener('blur', saveFormFields);
    });

    if (orderForm) {
        orderForm.addEventListener('input', validateFormState);
        orderForm.addEventListener('change', validateFormState);
        orderForm.addEventListener('keyup', validateFormState);
    }

    restoreFormFields();

    if (orderForm) {
        orderForm.addEventListener('submit', function (e) {
            if (!validatePhoneNumber() || !validateEmailAddress() || totalItemsCountGlobal === 0) {
                e.preventDefault();
                alert('Please check your inputs and make sure at least one product is selected with a valid 10-digit mobile number.');
                return false;
            }
            try {
                localStorage.removeItem('retail_cart_quantities');
                localStorage.removeItem('retail_customer_form');
            } catch (e) {}
            setTimeout(() => {
                submitBtn.setAttribute('disabled', 'disabled');
                submitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting Order...`;
            }, 10);
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-plus')) {
            const id = e.target.dataset.id;
            const input = document.getElementById(`qty-${id}`);
            if (input) {
                input.value = (parseInt(input.value) || 0) + 1;
                calculateTotals(true);
                const rect = e.target.getBoundingClientRect();
                const clickX = rect.left + rect.width / 2;
                const clickY = rect.top + rect.height / 2;
                triggerCrackerAt(clickX, clickY, 60);
            }
        } else if (e.target.classList.contains('btn-minus')) {
            const id = e.target.dataset.id;
            const input = document.getElementById(`qty-${id}`);
            if (input) {
                const currentVal = parseInt(input.value) || 0;
                if (currentVal > 0) {
                    input.value = currentVal - 1;
                    calculateTotals(true);
                }
            }
        }
    });

    qtyInputs.forEach(input => {
        input.addEventListener('input', function() {
            calculateTotals(true);
        });
    });

    window.addEventListener('scroll', function () {
        if (!scrollTopBtn) return;
        if (window.scrollY > 300) {
            scrollTopBtn.classList.add('show-scroll-btn');
        } else {
            scrollTopBtn.classList.remove('show-scroll-btn');
        }
    });

    if (scrollTopBtn) {
        scrollTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    function filterProductsBySearch() {
        if (!searchInput) return;
        const term = searchInput.value.toLowerCase().trim();
        const productRows = document.querySelectorAll('.product-card-row');
        let totalVisibleCount = 0;
        productRows.forEach(row => {
            const searchText = row.getAttribute('data-search-text');
            const visible = searchText.includes(term);
            row.style.display = visible ? '' : 'none';
            if (visible) totalVisibleCount++;
        });
        document.querySelectorAll('.category-block').forEach(block => {
            const rows = block.querySelectorAll('.product-card-row');
            const hasVisible = Array.from(rows).some(r => r.style.display !== 'none');
            block.style.display = hasVisible ? '' : 'none';
        });
        if (noSearchBanner) {
            noSearchBanner.style.display = (totalVisibleCount === 0 && term !== '') ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            if (clearSearchBtn) {
                clearSearchBtn.style.display = this.value.trim() !== '' ? 'inline-block' : 'none';
            }
            filterProductsBySearch();
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            if (!searchInput) return;
            searchInput.value = '';
            this.style.display = 'none';
            filterProductsBySearch();
        });
    }

    function applySortingLayout() {
        if (!sortSelect) return;
        const sortVal = sortSelect.value;
        const allRows = Array.from(document.querySelectorAll('.product-card-row'));
        if (sortVal === 'price_asc' || sortVal === 'price_desc') {
            if (categoryAccordionsWrapper) categoryAccordionsWrapper.style.display = 'none';
            if (sortedWrapper) sortedWrapper.style.display = 'block';
            allRows.sort((a, b) => parseFloat(sortVal === 'price_asc' ? a.dataset.price - b.dataset.price : b.dataset.price - a.dataset.price));
            allRows.forEach(row => sortedWrapper.appendChild(row));
        } else {
            if (sortedWrapper) sortedWrapper.style.display = 'none';
            if (categoryAccordionsWrapper) categoryAccordionsWrapper.style.display = 'block';
            document.querySelectorAll('.category-block').forEach(block => {
                const categoryName = block.dataset.categoryName;
                const container = block.querySelector('.product-container');
                let catRows = allRows.filter(row => row.dataset.category === categoryName);
                if (sortVal === 'cat_price_asc') {
                    catRows.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
                } else if (sortVal === 'cat_price_desc') {
                    catRows.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
                } else {
                    catRows.sort((a, b) => parseInt(a.dataset.id) - parseInt(b.dataset.id));
                }
                if (container) {
                    catRows.forEach(row => container.appendChild(row));
                }
            });
        }
        filterProductsBySearch();
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', applySortingLayout);
        applySortingLayout();
    }

    calculateTotals(false);
    validateFormState();
});
