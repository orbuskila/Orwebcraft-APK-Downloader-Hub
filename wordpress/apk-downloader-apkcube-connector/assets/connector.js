(function () {
    'use strict';

    function getPackage(input) {
        var value = String(input || '').trim();
        if (/^https?:\/\//i.test(value)) {
            try {
                var url = new URL(value);
                if (url.hostname.toLowerCase() !== 'play.google.com' || url.pathname !== '/store/apps/details') return '';
                value = url.searchParams.get('id') || '';
            } catch (e) { return ''; }
        }
        return value.length <= 255 && /^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/.test(value) ? value : '';
    }

    function start() {
        var form = document.getElementById('apkdownloader');
        var input = document.getElementById('region-package');
        var area = document.getElementById('ddea');
        var output = document.getElementById('downloader_area');
        var persistentLink = document.querySelector('.orw-apkcombo-always-link');
        if (!form || !input || !area || !output || form.dataset.orwApkcubeConnected) return;
        function updatePersistentLink() {
            if (!persistentLink) return;
            var packageId = getPackage(input.value);
            persistentLink.href = packageId
                ? 'https://apkcombo.com/downloader/#package=' + encodeURIComponent(packageId)
                : 'https://apkcombo.com/downloader/';
        }
        input.addEventListener('input', updatePersistentLink);
        updatePersistentLink();
        form.dataset.orwApkcubeConnected = '1';

        // Device selectors belonged to the retired service and are not APKCube inputs.
        ['countrytabs', 'opd', 'yid'].forEach(function (id) {
            var element = document.getElementById(id);
            if (element) element.style.display = 'none';
        });
        var note = document.createElement('p');
        note.textContent = 'Enter a package name or Google Play app link, then choose APKCombo or APKCube.';
        note.style.cssText = 'margin:12px 5px;color:#44576b;font-size:14px';
        var button = document.getElementById('apksubmit');
        if (button) button.parentNode.insertBefore(note, button);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            updatePersistentLink();
            var pkg = getPackage(input.value);
            area.style.display = 'block';
            while (output.firstChild) output.removeChild(output.firstChild);
            if (!pkg) {
                output.textContent = 'Enter a valid Google Play app link or package name.';
                return;
            }
            var title = document.createElement('p');
            title.textContent = 'Download options for ' + pkg;
            output.appendChild(title);

            var combo = document.createElement('a');
            combo.href = 'https://apkcombo.com/downloader/#package=' + encodeURIComponent(pkg);
            combo.target = '_blank';
            combo.rel = 'noopener noreferrer';
            combo.textContent = 'Open this app on APKCombo';
            combo.style.cssText = 'display:inline-block;margin:8px 0;padding:10px 16px;background:#00875f;color:#fff;border-radius:6px;text-decoration:none;font-weight:700';
            output.appendChild(combo);
            var comboNote = document.createElement('p');
            comboNote.textContent = 'APKCombo opens with this package selected. Complete its device and verification steps there to obtain the file link. Some apps are XAPK or APKS bundles.';
            output.appendChild(comboNote);

            var shareLabel = document.createElement('label');
            shareLabel.textContent = 'Link to APKCombo for this app (not a file URL)';
            shareLabel.style.cssText = 'display:block;margin:8px 0 4px;font-weight:600';
            var share = document.createElement('input');
            share.type = 'text';
            share.readOnly = true;
            share.value = combo.href;
            share.setAttribute('aria-label', 'APKCombo downloader link');
            share.style.cssText = 'box-sizing:border-box;width:100%;max-width:560px;padding:8px;border:1px solid #bbb;border-radius:5px';
            var copy = document.createElement('button');
            copy.type = 'button';
            copy.textContent = 'Copy APKCombo page link';
            copy.style.cssText = 'display:block;margin:8px 0;padding:8px 12px';
            copy.addEventListener('click', function () {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(share.value).then(function () {
                        copy.textContent = 'Copied';
                    }, function () {
                        share.select();
                        copy.textContent = 'Select Copy from your device menu';
                    });
                } else {
                    share.select();
                    copy.textContent = 'Select Copy from your device menu';
                }
            });
            output.appendChild(shareLabel);
            output.appendChild(share);
            output.appendChild(copy);

            var cubeTitle = document.createElement('p');
            cubeTitle.textContent = 'APKCube live app card:';
            output.appendChild(cubeTitle);
            var card = document.createElement('a');
            card.href = 'https://apkcube.com/go?q=' + encodeURIComponent(pkg) + '&to=download';
            card.target = '_blank';
            card.rel = 'nofollow noopener noreferrer';
            card.style.display = 'inline-block';
            var image = document.createElement('img');
            image.src = 'https://apkcube.com/embed/card/' + encodeURIComponent(pkg) + '.svg';
            image.alt = 'Download ' + pkg + ' from APKCube';
            image.width = 340;
            image.height = 116;
            image.loading = 'lazy';
            image.style.cssText = 'max-width:100%;height:auto';
            card.appendChild(image);
            output.appendChild(card);

            var fallback = document.createElement('p');
            var link = document.createElement('a');
            link.href = card.href;
            link.target = '_blank';
            link.rel = 'nofollow noopener noreferrer';
            link.textContent = 'Continue to APKCube download page';
            fallback.appendChild(link);
            output.appendChild(fallback);
        }, true);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}());
