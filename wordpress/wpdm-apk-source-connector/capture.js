(function () {
    'use strict';
    if (!window.owcApkWpdm || window.owcApkWpdmListening) return;
    window.owcApkWpdmListening = true;

    function packageId(value) {
        value = String(value || '').trim();
        if (/^https:\/\//i.test(value)) {
            try {
                var u = new URL(value);
                if (u.hostname === 'play.google.com' && u.pathname === '/store/apps/details') value = u.searchParams.get('id') || '';
                else if (u.hostname === 'apkcombo.com' || u.hostname === 'www.apkcombo.com') {
                    var match = u.pathname.match(/\/([A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+)\/?$/);
                    value = match ? match[1] : '';
                } else value = '';
            } catch (e) { value = ''; }
        }
        return value.length <= 180 && /^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/.test(value) ? value : '';
    }
    function isDownloaderForm(form) {
        return form && form.tagName === 'FORM' && form.id === 'apkdownloader';
    }
    function addStatus(form) {
        var status = form.querySelector('.owc-apk-wpdm-status') || form.nextElementSibling;
        if (!status || !status.classList.contains('owc-apk-wpdm-status')) {
            status = document.createElement('p');
            status.className = 'owc-apk-wpdm-status';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            form.insertAdjacentElement('afterend', status);
        }
        return status;
    }
    function appendLink(parent, text, url, external) {
        var link = document.createElement('a');
        link.textContent = text;
        link.href = url;
        if (external) { link.target = '_blank'; link.rel = 'nofollow noopener noreferrer'; }
        parent.appendChild(link);
    }
    function appendCopyField(parent, labelText, url, buttonText) {
        var row = document.createElement('div');
        row.style.marginTop = '8px';
        var label = document.createElement('label');
        label.textContent = labelText + ' ';
        var input = document.createElement('input');
        input.type = 'text';
        input.readOnly = true;
        input.value = url;
        input.setAttribute('aria-label', labelText);
        input.style.maxWidth = '100%';
        input.style.width = 'min(100%, 420px)';
        label.appendChild(input);
        row.appendChild(label);
        row.appendChild(document.createTextNode(' '));
        var copy = document.createElement('button');
        copy.type = 'button';
        copy.textContent = buttonText;
        copy.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () { copy.textContent = 'Copied!'; }, function () { input.focus(); input.select(); copy.textContent = 'Select and copy the link'; });
            } else { input.focus(); input.select(); copy.textContent = 'Select and copy the link'; }
        });
        row.appendChild(copy);
        parent.appendChild(row);
    }
    function appendApkcombo(status, data) {
        var steps = document.createElement('div');
        steps.style.marginTop = '12px';
        if (data.apkcombo_url) {
            appendCopyField(steps, 'APKCombo app page:', data.apkcombo_url, 'Copy APKCombo app link');
            appendLink(steps, 'Open this app on APKCombo', data.apkcombo_url, true);
        }
        var instruction = document.createElement('p');
        instruction.textContent = 'To use APKCombo Downloader, copy the package name or Google Play link and paste it into the downloader search box.';
        steps.appendChild(instruction);
        appendCopyField(steps, 'Package name:', data.package, 'Copy package name');
        appendCopyField(steps, 'Google Play link:', data.play_url, 'Copy Play link');
        var linkRow = document.createElement('div');
        linkRow.style.marginTop = '8px';
        appendLink(linkRow, 'Open APKCombo Downloader', data.apkcombo_downloader_url, true);
        steps.appendChild(linkRow);
        status.appendChild(steps);
    }
    function send(form, id, apkcombo, statusOverride) {
        if (!id || !form.isConnected) return;
        var owner = form;
        if (owner.dataset.owcSyncing === '1') return;
        owner.dataset.owcSyncing = '1';
        var status = statusOverride || addStatus(form);
        status.textContent = 'Adding ' + id + ' to Downloads…';
        return fetch(window.owcApkWpdm.url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
            body: new URLSearchParams({action: 'owc_apk_wpdm_capture', package: id, apkcombo: apkcombo || ''}).toString()
        }).then(function (response) {
            return response.text().then(function (raw) {
                var data;
                try { data = JSON.parse(raw); } catch (e) {
                    throw new Error((raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160) || 'Unexpected server response') + ' (HTTP ' + response.status + ')');
                }
                if (!response.ok || !data.success) {
                    var message = data.message || (typeof data.data === 'string' ? data.data : '') || 'Could not add the app.';
                    throw new Error(message + ' (HTTP ' + response.status + ')');
                }
                return data.data;
            });
        }).then(function (data) {
            if (data.published) {
                status.textContent = id + ' is published in Downloads. ';
                if (data.app_url) {
                    appendLink(status, 'View this app in Downloads', data.app_url, false);
                    status.appendChild(document.createTextNode(' · '));
                }
                appendLink(status, 'View archive', data.archive_url, false);
            } else status.textContent = 'The app was saved, but is not public. Check its WPDM status.';
            if (data.play_url && data.package) appendApkcombo(status, data);
        }).catch(function (error) {
            status.textContent = 'Could not add the app to Downloads: ' + error.message;
        }).finally(function () { owner.dataset.owcSyncing = '0'; });
    }
    function capture(form) {
        if (!isDownloaderForm(form)) return;
        var input = form.querySelector('#region-package');
        var id = input && packageId(input.value);
        var apkcombo = form.querySelector('.owc-apkcombo-app-link');
        // Let APK Downloader and APKCube finish their own event handlers first.
        if (id) window.setTimeout(function () { send(form, id, apkcombo && apkcombo.value.trim()); }, 0);
    }
    document.addEventListener('submit', function (event) {
        capture(event.target);
    }, true);
    document.addEventListener('click', function (event) {
        var copy = event.target.closest && event.target.closest('[data-owc-copy]');
        if (copy) {
            var value = copy.getAttribute('data-owc-copy');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () { copy.textContent = 'Copied!'; }, function () { copy.textContent = 'Select the link above to copy'; });
            } else {
                var field = copy.parentElement.querySelector('input');
                if (field) { field.focus(); field.select(); }
                copy.textContent = 'Select and copy manually';
            }
            return;
        }
        var button = event.target.closest && event.target.closest('#apksubmit');
        if (button) capture(button.form || button.closest('form'));
    }, true);
    function addArchiveButton(form) {
        if (!isDownloaderForm(form) || form.querySelector('.owc-apk-archive-action')) return;
        var wrapper = document.createElement('div');
        wrapper.className = 'owc-apk-archive-action';
        wrapper.style.cssText = 'margin:16px 0;padding:16px;border:1px solid #dce3e8;border-radius:10px;background:#f5fbf6';
        var label = document.createElement('label');
        label.textContent = 'APKCombo app page link (optional)';
        var field = document.createElement('input');
        field.className = 'owc-apkcombo-app-link';
        field.type = 'text';
        field.inputMode = 'url';
        field.placeholder = 'https://apkcombo.com/app-name/com.example.app/';
        field.style.cssText = 'display:block;width:100%;max-width:520px;margin:6px 0 12px';
        label.appendChild(field);
        wrapper.appendChild(label);
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'owc-apk-add-archive';
        button.textContent = 'Add this app to Downloads archive';
        button.style.cssText = 'padding:10px 16px;border:0;border-radius:8px;background:#207847;color:#fff;cursor:pointer';
        wrapper.appendChild(button);
        var status = document.createElement('div');
        status.className = 'owc-apk-wpdm-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        wrapper.appendChild(status);
        button.addEventListener('click', function () {
            var input = form.querySelector('#region-package');
            var id = input && packageId(input.value);
            if (!id) { status.textContent = 'Enter an Android package name or Google Play link above first.'; return; }
            send(form, id, field.value.trim(), status);
        });
        form.appendChild(wrapper);
    }
    function initButtons() {
        document.querySelectorAll('form#apkdownloader').forEach(addArchiveButton);
        if (!document.querySelector('form#apkdownloader') && window.MutationObserver && document.body) {
            var observer = new MutationObserver(function () {
                var form = document.querySelector('form#apkdownloader');
                if (form) { addArchiveButton(form); observer.disconnect(); }
            });
            observer.observe(document.body, {childList: true, subtree: true});
            window.setTimeout(function () { observer.disconnect(); }, 30000);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initButtons);
    else initButtons();
}());
