(function () {
    "use strict";
    var form = document.querySelector("[data-vk-vehicle-form]");
    var mapEl = document.getElementById("vkBookingMap");
    if (!form || !mapEl || typeof L === "undefined") return;

    var pickupInput = form.querySelector("[data-vk-pickup-input]");
    var dropInput = form.querySelector("[data-vk-drop-input]");
    var pickupList = form.querySelector("[data-vk-pickup-list]");
    var dropList = form.querySelector("[data-vk-drop-list]");
    var pickupLat = form.querySelector("[data-vk-pickup-lat]");
    var pickupLng = form.querySelector("[data-vk-pickup-lng]");
    var dropLat = form.querySelector("[data-vk-drop-lat]");
    var dropLng = form.querySelector("[data-vk-drop-lng]");
    var distanceEl = form.querySelector("[data-vk-distance]");
    var totalEl = form.querySelector("[data-vk-total]");
    var bookingTypeEl = form.querySelector("[data-vk-booking-type]");
    var vehicleSelect = form.querySelector("[data-vk-vehicle-select]");

    var map = L.map(mapEl).setView([9.3803, 80.3761], 10);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);

    var pickupMarker = null;
    var dropMarker = null;
    var routeLayer = null;

    function setMarker(lat, lng, type) {
        var marker = L.marker([lat, lng]);
        if (type === "pickup") {
            if (pickupMarker) map.removeLayer(pickupMarker);
            pickupMarker = marker.addTo(map);
        } else {
            if (dropMarker) map.removeLayer(dropMarker);
            dropMarker = marker.addTo(map);
        }
        fitBounds();
    }

    function fitBounds() {
        var pts = [];
        if (pickupMarker) pts.push(pickupMarker.getLatLng());
        if (dropMarker) pts.push(dropMarker.getLatLng());
        if (pts.length === 2) map.fitBounds(L.latLngBounds(pts), { padding: [20, 20] });
        else if (pts.length === 1) map.setView(pts[0], 13);
    }

    function debounce(fn, delay) {
        var t = null;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    function renderList(listEl, items, onPick) {
        listEl.innerHTML = "";
        if (!items.length) {
            listEl.classList.add("d-none");
            return;
        }
        items.slice(0, 6).forEach(function (it) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "vk-location-option";
            btn.textContent = it.display_name;
            btn.addEventListener("click", function () { onPick(it); });
            listEl.appendChild(btn);
        });
        listEl.classList.remove("d-none");
    }

    function lookup(query, cb) {
        if (!query || query.length < 3) return cb([]);
        var url = "https://nominatim.openstreetmap.org/search?format=jsonv2&q=" + encodeURIComponent(query) + "&limit=6";
        fetch(url, { headers: { Accept: "application/json" } })
            .then(function (r) { return r.json(); })
            .then(function (rows) { cb(Array.isArray(rows) ? rows : []); })
            .catch(function () { cb([]); });
    }

    function recalcPrice() {
        var selected = vehicleSelect.options[vehicleSelect.selectedIndex];
        var perDay = Number((selected && selected.getAttribute("data-day")) || 0);
        var perKm = Number((selected && selected.getAttribute("data-km")) || 0);
        var driverCharge = Number((selected && selected.getAttribute("data-driver-charge")) || 0);
        var distance = Number(distanceEl.value || 0);
        var pickupAt = form.querySelector("[name='pickup_at']").value;
        var returnAt = form.querySelector("[name='return_at']").value;
        var days = 1;
        if (pickupAt && returnAt) {
            var s = new Date(pickupAt).getTime();
            var e = new Date(returnAt).getTime();
            if (e > s) days = Math.max(1, Math.ceil((e - s) / 86400000));
        }
        var total = 0;
        if ((bookingTypeEl.value || "rental") === "hire") total = (distance * perKm) + driverCharge;
        else total = perDay * days;
        if (typeof formatCurrency === "function") {
            totalEl.textContent = formatCurrency(total);
        } else {
            var n = Number(total);
            if (!Number.isFinite(n)) n = 0;
            var fixed = n.toFixed(2);
            var parts = fixed.split(".");
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            totalEl.textContent = "Rs. " + parts.join(".");
        }
    }

    function updateRoute() {
        var aLat = Number(pickupLat.value || 0);
        var aLng = Number(pickupLng.value || 0);
        var bLat = Number(dropLat.value || 0);
        var bLng = Number(dropLng.value || 0);
        if (!aLat || !aLng || !bLat || !bLng) {
            if (routeLayer) { map.removeLayer(routeLayer); routeLayer = null; }
            distanceEl.value = "0";
            recalcPrice();
            return;
        }
        var osrm = "https://router.project-osrm.org/route/v1/driving/" + aLng + "," + aLat + ";" + bLng + "," + bLat + "?overview=full&geometries=geojson";
        fetch(osrm)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var route = data && data.routes && data.routes[0];
                if (!route) return;
                var km = (Number(route.distance || 0) / 1000);
                distanceEl.value = km.toFixed(2);
                if (routeLayer) map.removeLayer(routeLayer);
                routeLayer = L.geoJSON(route.geometry, { style: { color: "#2563eb", weight: 5, opacity: 0.8 } }).addTo(map);
                fitBounds();
                recalcPrice();
            })
            .catch(function () {
                recalcPrice();
            });
    }

    pickupInput.addEventListener("input", debounce(function () {
        lookup(pickupInput.value, function (items) {
            renderList(pickupList, items, function (it) {
                pickupInput.value = it.display_name;
                pickupLat.value = String(it.lat);
                pickupLng.value = String(it.lon);
                pickupList.classList.add("d-none");
                setMarker(Number(it.lat), Number(it.lon), "pickup");
                updateRoute();
            });
        });
    }, 280));

    dropInput.addEventListener("input", debounce(function () {
        lookup(dropInput.value, function (items) {
            renderList(dropList, items, function (it) {
                dropInput.value = it.display_name;
                dropLat.value = String(it.lat);
                dropLng.value = String(it.lon);
                dropList.classList.add("d-none");
                setMarker(Number(it.lat), Number(it.lon), "drop");
                updateRoute();
            });
        });
    }, 280));

    ["change", "input"].forEach(function (ev) {
        bookingTypeEl.addEventListener(ev, recalcPrice);
        vehicleSelect.addEventListener(ev, recalcPrice);
        form.querySelector("[name='pickup_at']").addEventListener(ev, recalcPrice);
        form.querySelector("[name='return_at']").addEventListener(ev, recalcPrice);
    });

    document.addEventListener("click", function (e) {
        if (!pickupList.contains(e.target) && e.target !== pickupInput) pickupList.classList.add("d-none");
        if (!dropList.contains(e.target) && e.target !== dropInput) dropList.classList.add("d-none");
    });

    recalcPrice();
})();
