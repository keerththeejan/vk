(function () {
    'use strict';

    var SL_CENTER = [7.8731, 80.7718];
    var SL_ZOOM = 7;

    function init() {
        var mapEl = document.getElementById('map') || document.getElementById('st_location_map');
        if (!mapEl || typeof L === 'undefined') return;

        var latInput = document.getElementById('st_loc_lat');
        var lngInput = document.getElementById('st_loc_lng');
        var clearFlag = document.getElementById('st_loc_clear_flag');
        var searchInput = document.getElementById('st_loc_search');
        var searchBtn = document.getElementById('st_loc_search_btn');
        var searchStatus = document.getElementById('st_loc_search_status');
        var clearBtn = document.getElementById('st_loc_clear_btn');

        var map = L.map(mapEl, { scrollWheelZoom: true }).setView(SL_CENTER, SL_ZOOM);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        var marker = null;

        function setMarker(latlng) {
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                updateInputs(p.lat, p.lng);
                if (clearFlag) clearFlag.value = '0';
            });
            updateInputs(latlng.lat, latlng.lng);
            if (clearFlag) clearFlag.value = '0';
        }

        function updateInputs(lat, lng) {
            if (latInput) latInput.value = lat.toFixed(8);
            if (lngInput) lngInput.value = lng.toFixed(8);
        }

        function readInitial() {
            var la = latInput && latInput.value ? parseFloat(latInput.value) : NaN;
            var ln = lngInput && lngInput.value ? parseFloat(lngInput.value) : NaN;
            if (!isNaN(la) && !isNaN(ln)) {
                map.setView([la, ln], 14);
                setMarker(L.latLng(la, ln));
            }
        }

        readInitial();

        map.on('click', function (e) {
            setMarker(e.latlng);
            map.panTo(e.latlng);
        });

        function nominatimSearch(q) {
            q = (q || '').trim();
            if (!q) return;
            if (searchStatus) {
                searchStatus.textContent = 'Searching…';
            }
            var url =
                'https://nominatim.openstreetmap.org/search?q=' +
                encodeURIComponent(q) +
                '&format=json&limit=5&addressdetails=0';
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
                        if (searchStatus) searchStatus.textContent = 'No results. Try another query.';
                        return;
                    }
                    var first = data[0];
                    var lat = parseFloat(first.lat);
                    var lon = parseFloat(first.lon);
                    if (isNaN(lat) || isNaN(lon)) return;
                    map.setView([lat, lon], 15);
                    setMarker(L.latLng(lat, lon));
                    if (searchStatus) searchStatus.textContent = 'Found: ' + (first.display_name || '').slice(0, 80);
                })
                .catch(function () {
                    if (searchStatus) searchStatus.textContent = 'Search failed. Try again.';
                });
        }

        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', function () {
                nominatimSearch(searchInput.value);
            });
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nominatimSearch(searchInput.value);
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                if (latInput) latInput.value = '';
                if (lngInput) lngInput.value = '';
                if (clearFlag) clearFlag.value = '1';
                map.setView(SL_CENTER, SL_ZOOM);
                if (searchStatus) searchStatus.textContent = 'Location cleared.';
            });
        }

        function refreshMapSize() {
            if (map && map.invalidateSize) {
                map.invalidateSize();
            }
        }

        setTimeout(refreshMapSize, 100);
        setTimeout(refreshMapSize, 400);
        window.addEventListener('load', refreshMapSize);
        if (window.requestAnimationFrame) {
            requestAnimationFrame(function () {
                requestAnimationFrame(refreshMapSize);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
