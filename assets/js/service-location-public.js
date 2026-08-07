(function () {
    'use strict';

    function haversineKm(lat1, lon1, lat2, lon2) {
        var R = 6371;
        var dLat = ((lat2 - lat1) * Math.PI) / 180;
        var dLon = ((lon2 - lon1) * Math.PI) / 180;
        var a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos((lat1 * Math.PI) / 180) *
                Math.cos((lat2 * Math.PI) / 180) *
                Math.sin(dLon / 2) *
                Math.sin(dLon / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function init() {
        var mapEl = document.getElementById('map') || document.getElementById('vk_svc_public_map');
        if (!mapEl || typeof L === 'undefined') return;

        var lat = parseFloat(mapEl.getAttribute('data-lat') || '');
        var lng = parseFloat(mapEl.getAttribute('data-lng') || '');
        if (isNaN(lat) || isNaN(lng)) return;

        var distEl = document.getElementById('vk_svc_loc_distance');
        var geoBtn = document.getElementById('vk_svc_loc_geolocate');

        var map = L.map(mapEl, { scrollWheelZoom: true }).setView([lat, lng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        L.marker([lat, lng]).addTo(map);

        function showDistance(userLat, userLng) {
            if (!distEl) return;
            var km = haversineKm(userLat, userLng, lat, lng);
            if (km < 1) {
                distEl.textContent = 'About ' + Math.round(km * 1000) + ' m from you';
            } else {
                distEl.textContent = 'About ' + km.toFixed(1) + ' km from you';
            }
            distEl.classList.remove('d-none');
        }

        if (geoBtn && navigator.geolocation) {
            geoBtn.addEventListener('click', function () {
                geoBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var ulat = pos.coords.latitude;
                        var ulng = pos.coords.longitude;
                        showDistance(ulat, ulng);
                        L.circleMarker([ulat, ulng], {
                            radius: 8,
                            fillColor: '#3b82f6',
                            color: '#fff',
                            weight: 2,
                            fillOpacity: 0.9,
                        })
                            .addTo(map)
                            .bindPopup('Your location');
                        map.fitBounds(
                            [
                                [lat, lng],
                                [ulat, ulng],
                            ],
                            { padding: [40, 40], maxZoom: 12 }
                        );
                        geoBtn.disabled = false;
                    },
                    function () {
                        if (distEl) {
                            distEl.textContent = 'Could not access your location.';
                            distEl.classList.remove('d-none');
                        }
                        geoBtn.disabled = false;
                    },
                    { enableHighAccuracy: true, timeout: 12000 }
                );
            });
        } else if (geoBtn) {
            geoBtn.classList.add('d-none');
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
