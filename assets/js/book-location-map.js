(function () {
    'use strict';

    var SL_CENTER = [7.8731, 80.7718];
    var SL_ZOOM = 7;

    function init() {
        var form = document.getElementById('bookForm');
        var mapEl = document.getElementById('map');
        if (!form || !mapEl || typeof L === 'undefined') return;

        var latEl = form.querySelector('[name="latitude"]');
        var lngEl = form.querySelector('[name="longitude"]');
        if (!latEl || !lngEl) return;

        var searchInput = document.getElementById('locationSearch');
        var btnGeo = document.getElementById('btnGeo');
        var btnClear = document.getElementById('btnClearLoc');

        var map = L.map(mapEl, { scrollWheelZoom: true }).setView(SL_CENTER, SL_ZOOM);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        var marker = null;

        function setInputs(lat, lng) {
            latEl.value = typeof lat === 'number' ? lat.toFixed(7) : String(lat);
            lngEl.value = typeof lng === 'number' ? lng.toFixed(7) : String(lng);
        }

        function placeMarker(latlng) {
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                setInputs(p.lat, p.lng);
            });
            setInputs(latlng.lat, latlng.lng);
        }

        var initLat = parseFloat(String(latEl.value).trim());
        var initLng = parseFloat(String(lngEl.value).trim());
        if (!isNaN(initLat) && !isNaN(initLng)) {
            map.setView([initLat, initLng], 14);
            placeMarker(L.latLng(initLat, initLng));
        }

        map.on('click', function (e) {
            placeMarker(e.latlng);
        });

        function nominatimSearch(q) {
            q = (q || '').trim();
            if (!q) return;
            var url =
                'https://nominatim.openstreetmap.org/search?format=json&q=' +
                encodeURIComponent(q) +
                '&limit=5&addressdetails=0';
            fetch(url, {
                method: 'GET',
                mode: 'cors',
                credentials: 'omit',
                headers: {
                    Accept: 'application/json',
                    'Accept-Language': 'en',
                },
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !data.length) return;
                    var lat = parseFloat(data[0].lat);
                    var lon = parseFloat(data[0].lon);
                    if (isNaN(lat) || isNaN(lon)) return;
                    map.setView([lat, lon], 13);
                    placeMarker(L.latLng(lat, lon));
                })
                .catch(function () {});
        }

        if (searchInput) {
            searchInput.addEventListener('change', function () {
                nominatimSearch(searchInput.value);
            });
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nominatimSearch(searchInput.value);
                }
            });
        }

        if (btnGeo && navigator.geolocation) {
            btnGeo.addEventListener('click', function () {
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        map.setView([lat, lng], 14);
                        placeMarker(L.latLng(lat, lng));
                    },
                    function () {},
                    { enableHighAccuracy: true, timeout: 15000 }
                );
            });
        } else if (btnGeo) {
            btnGeo.classList.add('d-none');
        }

        if (btnClear) {
            btnClear.addEventListener('click', function () {
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                latEl.value = '';
                lngEl.value = '';
                map.setView(SL_CENTER, SL_ZOOM);
            });
        }

        function invalidate() {
            map.invalidateSize();
        }
        setTimeout(invalidate, 100);
        setTimeout(invalidate, 400);
        window.addEventListener('load', invalidate);
        if (window.requestAnimationFrame) {
            requestAnimationFrame(function () {
                requestAnimationFrame(invalidate);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
