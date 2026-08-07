(function () {
    'use strict';

    var SL_CENTER = [7.8731, 80.7718];
    var SL_ZOOM = 7;

    function init() {
        var mapEl = document.getElementById('techMap');
        if (!mapEl || typeof L === 'undefined') {
            return;
        }

        var latIn = document.getElementById('latitude');
        var lngIn = document.getElementById('longitude');
        if (!latIn || !lngIn) {
            return;
        }

        var searchIn = document.getElementById('techLocationSearch');
        var statusEl = document.getElementById('techLocationSearchStatus');

        var map = L.map(mapEl, { scrollWheelZoom: true }).setView(SL_CENTER, SL_ZOOM);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        var marker = null;

        function updateInputs(lat, lng) {
            latIn.value = typeof lat === 'number' ? lat.toFixed(8) : String(lat);
            lngIn.value = typeof lng === 'number' ? lng.toFixed(8) : String(lng);
        }

        function placeMarker(latlng) {
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                updateInputs(p.lat, p.lng);
            });
            updateInputs(latlng.lat, latlng.lng);
        }

        function readInitial() {
            var la = latIn.value ? parseFloat(latIn.value) : NaN;
            var ln = lngIn.value ? parseFloat(lngIn.value) : NaN;
            if (!isNaN(la) && !isNaN(ln)) {
                map.setView([la, ln], 14);
                placeMarker(L.latLng(la, ln));
            }
        }

        readInitial();

        map.on('click', function (e) {
            placeMarker(e.latlng);
        });

        function nominatimSearch(q) {
            q = (q || '').trim();
            if (!q) {
                return;
            }
            if (statusEl) {
                statusEl.textContent = 'Searching…';
            }
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
                    if (!data || !data.length) {
                        if (statusEl) {
                            statusEl.textContent = 'No results. Try another search.';
                        }
                        return;
                    }
                    var first = data[0];
                    var lat = parseFloat(first.lat);
                    var lon = parseFloat(first.lon);
                    if (isNaN(lat) || isNaN(lon)) {
                        return;
                    }
                    map.setView([lat, lon], 13);
                    placeMarker(L.latLng(lat, lon));
                    if (statusEl) {
                        statusEl.textContent = (first.display_name || 'Found').slice(0, 100);
                    }
                })
                .catch(function () {
                    if (statusEl) {
                        statusEl.textContent = 'Search failed. Try again.';
                    }
                });
        }

        if (searchIn) {
            searchIn.addEventListener('change', function () {
                nominatimSearch(searchIn.value);
            });
            searchIn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nominatimSearch(searchIn.value);
                }
            });
        }

        function refreshSize() {
            map.invalidateSize();
        }
        setTimeout(refreshSize, 100);
        setTimeout(refreshSize, 400);
        window.addEventListener('load', refreshSize);
        if (window.requestAnimationFrame) {
            requestAnimationFrame(function () {
                requestAnimationFrame(refreshSize);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
