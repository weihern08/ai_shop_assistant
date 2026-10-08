/**
 * Customer scanner: camera, barcode (ZXing), capture, AI recognition.
 */
(function () {
    'use strict';

    var config = window.SCAN_CONFIG || {};
    var video = document.getElementById('cameraVideo');
    var canvas = document.getElementById('captureCanvas');
    var preview = document.getElementById('capturedPreview');
    var statusEl = document.getElementById('scanStatus');
    var resultPanel = document.getElementById('resultPanel');
    var scanFrame = document.getElementById('scanFrame');
    var scanLine = document.getElementById('scanLine');
    var btnCapture = document.getElementById('btnCapture');
    var btnRetake = document.getElementById('btnRetake');
    var btnSwitch = document.getElementById('btnSwitch');

    var stream = null;
    var facingMode = 'environment';
    var barcodeBusy = false;
    var lastBarcode = '';
    var lastBarcodeAt = 0;
    var pendingCode = '';
    var pendingCount = 0;
    var scanning = true;
    var codeReader = null;
    var scanTimer = null;
    var recognizing = false;

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    function stopBarcodeLoop() {
        scanning = false;
        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }
    }

    function supportsCamera() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    function isSecureContextOk() {
        return window.isSecureContext === true
            || location.protocol === 'https:'
            || location.hostname === 'localhost'
            || location.hostname === '127.0.0.1';
    }

    async function startCamera() {
        // HTTP + LAN IP makes mediaDevices undefined — check HTTPS first
        if (!isSecureContextOk()) {
            setStatus('Camera needs HTTPS. Open the HTTPS scan URL (accept certificate), then allow camera.');
            return;
        }
        if (!supportsCamera()) {
            setStatus('Camera API unavailable. Use Chrome/Safari and allow camera permission.');
            return;
        }

        setStatus('Starting camera...');
        stopStream();

        var constraints = {
            audio: false,
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: true
                });
            } catch (err2) {
                var msg = 'Unable to access your camera. Please allow camera permission and try again.';
                if (err2 && (err2.name === 'NotFoundError' || err2.name === 'DevicesNotFoundError')) {
                    msg = 'No camera was found on this device.';
                } else if (err2 && (err2.name === 'NotAllowedError' || err2.name === 'PermissionDeniedError')) {
                    msg = 'Camera permission denied. Please allow camera access and reload.';
                }
                setStatus(msg);
                return;
            }
        }

        video.srcObject = stream;
        video.setAttribute('playsinline', 'true');
        await video.play().catch(function () {});
        setStatus('Camera ready');
        showLive();
        scanning = true;
        scheduleBarcodeScan();
        setTimeout(function () {
            if (scanning && !recognizing) setStatus('Looking for barcode...');
        }, 600);
    }

    function showLive() {
        video.hidden = false;
        preview.hidden = true;
        if (scanFrame) scanFrame.hidden = false;
        if (scanLine) scanLine.hidden = false;
        btnCapture.disabled = false;
        btnRetake.disabled = true;
        resultPanel.hidden = true;
        resultPanel.innerHTML = '';
    }

    function showCaptured(dataUrl) {
        preview.src = dataUrl;
        preview.hidden = false;
        video.hidden = true;
        if (scanFrame) scanFrame.hidden = true;
        if (scanLine) scanLine.hidden = true;
        btnCapture.disabled = true;
        btnRetake.disabled = false;
    }

    function captureFrame() {
        if (!video || !video.videoWidth) {
            setStatus('Camera is not ready yet.');
            return null;
        }
        var w = video.videoWidth;
        var h = video.videoHeight;
        var max = 1280;
        var scale = Math.min(1, max / Math.max(w, h));
        canvas.width = Math.round(w * scale);
        canvas.height = Math.round(h * scale);
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.82);
    }

    function barcodeFormats() {
        if (!(ZXing && ZXing.BarcodeFormat)) return null;
        var f = [
            ZXing.BarcodeFormat.EAN_13,
            ZXing.BarcodeFormat.EAN_8,
            ZXing.BarcodeFormat.UPC_A,
            ZXing.BarcodeFormat.UPC_E,
            ZXing.BarcodeFormat.CODE_128,
            ZXing.BarcodeFormat.CODE_39,
            ZXing.BarcodeFormat.QR_CODE
        ];
        if (ZXing.BarcodeFormat.ITF) f.push(ZXing.BarcodeFormat.ITF);
        if (ZXing.BarcodeFormat.CODABAR) f.push(ZXing.BarcodeFormat.CODABAR);
        if (ZXing.BarcodeFormat.RSS_14) f.push(ZXing.BarcodeFormat.RSS_14);
        return f;
    }

    function applyBarcodeHints(reader) {
        if (!reader || !ZXing.DecodeHintType) return;
        var hints = new Map();
        var formats = barcodeFormats();
        if (formats) hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, formats);
        hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
        if (ZXing.DecodeHintType.ALSO_INVERTED != null) {
            hints.set(ZXing.DecodeHintType.ALSO_INVERTED, true);
        }
        reader.setHints(hints);
    }

    function getZXingReader() {
        if (codeReader) return codeReader;
        // Prefer canvas + MultiFormatReader (reliable with @zxing/library UMD)
        if (typeof ZXing !== 'undefined' && ZXing.MultiFormatReader && ZXing.RGBLuminanceSource) {
            var reader = new ZXing.MultiFormatReader();
            applyBarcodeHints(reader);
            codeReader = { _legacy: true, reader: reader };
            return codeReader;
        }
        if (typeof ZXing !== 'undefined' && ZXing.BrowserMultiFormatReader) {
            codeReader = new ZXing.BrowserMultiFormatReader();
            return codeReader;
        }
        if (typeof ZXingBrowser !== 'undefined' && ZXingBrowser.BrowserMultiFormatReader) {
            codeReader = new ZXingBrowser.BrowserMultiFormatReader();
            return codeReader;
        }
        return null;
    }

    function invertImageData(imageData) {
        var d = imageData.data;
        for (var i = 0; i < d.length; i += 4) {
            d[i] = 255 - d[i];
            d[i + 1] = 255 - d[i + 1];
            d[i + 2] = 255 - d[i + 2];
        }
        return imageData;
    }

    function decodeImageData(reader, imageData) {
        var luminance = new ZXing.RGBLuminanceSource(imageData.data, imageData.width, imageData.height);
        var attempts = [
            new ZXing.HybridBinarizer(luminance)
        ];
        if (ZXing.GlobalHistogramBinarizer) {
            attempts.push(new ZXing.GlobalHistogramBinarizer(luminance));
        }
        var lastErr = null;
        for (var i = 0; i < attempts.length; i++) {
            try {
                if (reader.reset) reader.reset();
                applyBarcodeHints(reader);
                return reader.decode(new ZXing.BinaryBitmap(attempts[i]));
            } catch (e) {
                lastErr = e;
            }
        }
        throw lastErr || new Error('not found');
    }

    /**
     * Decode barcode from live video — full frame + center crop + invert.
     * Eco-Shop style short codes (Code 128 / EAN-8) need higher resolution.
     */
    function decodeFromCanvasLegacy(readerWrap) {
        var vw = video.videoWidth;
        var vh = video.videoHeight;
        if (!vw || !vh) throw new Error('no video');

        var maxW = 1280;
        var scale = Math.min(1, maxW / vw);
        var fullW = Math.round(vw * scale);
        var fullH = Math.round(vh * scale);
        var tmp = document.createElement('canvas');
        var ctx = tmp.getContext('2d', { willReadFrequently: true });
        var reader = readerWrap.reader;

        // 1) Full frame
        tmp.width = fullW;
        tmp.height = fullH;
        ctx.drawImage(video, 0, 0, fullW, fullH);
        try {
            return decodeImageData(reader, ctx.getImageData(0, 0, fullW, fullH));
        } catch (e1) { /* continue */ }

        // 2) Center crop (matches on-screen scan frame)
        var cropW = Math.round(fullW * 0.72);
        var cropH = Math.round(fullH * 0.42);
        var sx = Math.round((fullW - cropW) / 2);
        var sy = Math.round((fullH - cropH) / 2);
        var crop = document.createElement('canvas');
        crop.width = cropW;
        crop.height = cropH;
        var cctx = crop.getContext('2d', { willReadFrequently: true });
        cctx.drawImage(tmp, sx, sy, cropW, cropH, 0, 0, cropW, cropH);
        try {
            return decodeImageData(reader, cctx.getImageData(0, 0, cropW, cropH));
        } catch (e2) { /* continue */ }

        // 3) Inverted center crop (helps shiny / reflective labels)
        var inv = cctx.getImageData(0, 0, cropW, cropH);
        invertImageData(inv);
        return decodeImageData(reader, inv);
    }

    async function tryDecodeBarcode() {
        if (!scanning || recognizing || barcodeBusy || video.hidden || !video.videoWidth) return;
        var reader = getZXingReader();
        if (!reader) {
            setStatus('Barcode library missing. Reload the page.');
            return;
        }

        barcodeBusy = true;
        try {
            var result = null;
            if (reader._legacy) {
                result = decodeFromCanvasLegacy(reader);
            } else if (reader.decodeOnceFromVideoElement) {
                result = await Promise.race([
                    reader.decodeOnceFromVideoElement(video),
                    new Promise(function (_, reject) { setTimeout(function () { reject(new Error('timeout')); }, 400); })
                ]);
            }

            var text = null;
            if (result && result.getText) text = result.getText();
            else if (result && result.text) text = result.text;

            if (text) {
                text = String(text).trim();
                // Require 2 consecutive identical reads to avoid false positives
                if (text === pendingCode) {
                    pendingCount += 1;
                } else {
                    pendingCode = text;
                    pendingCount = 1;
                    setStatus('Reading barcode… ' + text);
                }
                if (pendingCount >= 2) {
                    pendingCode = '';
                    pendingCount = 0;
                    handleBarcode(text);
                }
            }
        } catch (e) {
            // NotFoundException is normal — ignore
        } finally {
            barcodeBusy = false;
        }
    }

    function scheduleBarcodeScan() {
        if (!scanning) return;
        scanTimer = setTimeout(async function () {
            await tryDecodeBarcode();
            scheduleBarcodeScan();
        }, 180);
    }

    async function handleBarcode(code) {
        code = String(code || '').trim();
        if (!code) return;

        var now = Date.now();
        if (code === lastBarcode && now - lastBarcodeAt < 4000) return;
        lastBarcode = code;
        lastBarcodeAt = now;

        setStatus('Barcode detected: ' + code);
        stopBarcodeLoop();
        recognizing = true;
        btnCapture.disabled = true;

        try {
            var res = await fetch(config.barcodeApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ barcode: code })
            });
            var data = await res.json();
            if (data.success && data.found && data.product) {
                setStatus('Product found');
                renderProduct(data.product, 'barcode');
            } else {
                setStatus('Barcode detected, but this product is not in the catalog.');
                renderNotFound(data.message || 'Barcode detected, but this product is not in the catalog.', code);
                scanning = true;
                scheduleBarcodeScan();
            }
        } catch (err) {
            setStatus('Unable to look up barcode. Please try again.');
            scanning = true;
            scheduleBarcodeScan();
        } finally {
            recognizing = false;
            btnCapture.disabled = false;
            btnRetake.disabled = false;
        }
    }

    function tryDecodeBarcodeFromDataUrl() {
        try {
            if (typeof ZXing === 'undefined' || !ZXing.MultiFormatReader || !ZXing.RGBLuminanceSource) {
                return null;
            }
            // Use the canvas just filled by captureFrame()
            if (!canvas.width || !canvas.height) return null;
            var ctx = canvas.getContext('2d', { willReadFrequently: true });
            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var luminance = new ZXing.RGBLuminanceSource(imageData.data, canvas.width, canvas.height);
            var binary = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(luminance));
            var reader = new ZXing.MultiFormatReader();
            var hints = new Map();
            if (ZXing.DecodeHintType && ZXing.BarcodeFormat) {
                hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                    ZXing.BarcodeFormat.EAN_13,
                    ZXing.BarcodeFormat.EAN_8,
                    ZXing.BarcodeFormat.UPC_A,
                    ZXing.BarcodeFormat.UPC_E,
                    ZXing.BarcodeFormat.CODE_128,
                    ZXing.BarcodeFormat.CODE_39,
                    ZXing.BarcodeFormat.ITF,
                    ZXing.BarcodeFormat.QR_CODE
                ]);
                hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
                reader.setHints(hints);
            }
            var result = reader.decode(binary);
            if (result && result.getText) return result.getText();
            if (result && result.text) return result.text;
        } catch (e) {
            // no barcode in frame
        }
        return null;
    }

    async function recognizeImage(dataUrl) {
        recognizing = true;
        stopBarcodeLoop();
        showCaptured(dataUrl);

        // Prefer barcode from the captured frame (faster + no temp file / AI needed)
        setStatus('Reading barcode...');
        var code = tryDecodeBarcodeFromDataUrl();
        if (code) {
            recognizing = false;
            await handleBarcode(code);
            if (resultPanel && !resultPanel.hidden && resultPanel.querySelector('.price-tag')) {
                btnRetake.disabled = false;
                return;
            }
            // Barcode read but not in catalog — fall through to AI
            recognizing = true;
        }

        setStatus('Identifying product with AI...');

        try {
            var res = await fetch(config.recognizeApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ image: dataUrl })
            });
            var data = await res.json();
            if (data.success && data.found && data.product) {
                setStatus('Product found');
                renderProduct(data.product, 'ai');
            } else {
                var msg = data.message || 'Unable to identify the product. Please try another photo.';
                setStatus(msg);
                renderNotFound(msg);
                // Resume live barcode scanning after failure
                scanning = true;
                scheduleBarcodeScan();
            }
        } catch (err) {
            setStatus('Unable to identify the product. Please try another photo.');
            renderNotFound('Unable to identify the product. Please try another photo.');
            scanning = true;
            scheduleBarcodeScan();
        } finally {
            recognizing = false;
            btnRetake.disabled = false;
            btnCapture.disabled = false;
        }
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderProduct(product, source) {
        var img = product.image_url || product.image || config.placeholder;
        var conf = '';
        if (typeof product.confidence === 'number') {
            conf = '<span class="confidence-badge"><i class="fa-solid fa-chart-simple"></i> Confidence: '
                + Math.round(product.confidence * 100) + '%</span>';
        }
        var barcode = product.barcode
            ? '<span class="meta-chip"><i class="fa-solid fa-barcode"></i> ' + escapeHtml(product.barcode) + '</span>'
            : '';

        resultPanel.hidden = false;
        resultPanel.innerHTML =
            '<img class="result-image" src="' + escapeHtml(img) + '" alt="' + escapeHtml(product.name) + '">' +
            '<div class="result-body">' +
            '<div class="result-name">' + escapeHtml(product.name) + '</div>' +
            '<div class="price-tag mb-3"><i class="fa-solid fa-tag"></i> RM ' + escapeHtml(product.price) + '</div>' +
            '<div class="mb-2">' +
            '<span class="meta-chip">SKU: ' + escapeHtml(product.sku) + '</span>' + barcode +
            '</div>' +
            (product.description ? '<p class="mb-3">' + escapeHtml(product.description) + '</p>' : '') +
            conf +
            '<div class="mt-3 d-grid">' +
            '<button type="button" class="btn btn-brand" id="btnScanAgain"><i class="fa-solid fa-rotate-left me-1"></i>Scan another</button>' +
            '</div>' +
            '<p class="small text-muted mt-2 mb-0">Matched via ' + escapeHtml(source === 'ai' ? 'AI + catalog' : 'barcode') + '. Price from store database.</p>' +
            '</div>';

        var again = document.getElementById('btnScanAgain');
        if (again) again.addEventListener('click', retake);
    }

    function renderNotFound(message, barcode) {
        resultPanel.hidden = false;
        resultPanel.innerHTML =
            '<div class="result-body">' +
            '<div class="result-name">Product not found</div>' +
            '<p class="text-muted">' + escapeHtml(message) + '</p>' +
            (barcode ? '<p class="meta-chip mb-3"><i class="fa-solid fa-barcode"></i> ' + escapeHtml(barcode) + '</p>' : '') +
            '<p class="small mb-3">Try Capture for AI recognition, or Retake for another scan.</p>' +
            '<div class="d-grid gap-2">' +
            '<button type="button" class="btn btn-capture" id="btnTryAi"><i class="fa-solid fa-robot me-1"></i>Capture for AI</button>' +
            '<button type="button" class="btn btn-soft" id="btnScanAgain2"><i class="fa-solid fa-rotate-left me-1"></i>Keep scanning</button>' +
            '</div></div>';

        var tryAi = document.getElementById('btnTryAi');
        if (tryAi) {
            tryAi.addEventListener('click', function () {
                var dataUrl = captureFrame();
                if (dataUrl) recognizeImage(dataUrl);
            });
        }
        var again = document.getElementById('btnScanAgain2');
        if (again) again.addEventListener('click', retake);
    }

    function retake() {
        lastBarcode = '';
        pendingCode = '';
        pendingCount = 0;
        recognizing = false;
        resultPanel.hidden = true;
        resultPanel.innerHTML = '';
        showLive();
        setStatus('Looking for barcode...');
        scanning = true;
        scheduleBarcodeScan();
        if (!stream) startCamera();
        else video.play().catch(function () {});
    }

    async function switchCamera() {
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        setStatus('Switching camera...');
        stopBarcodeLoop();
        await startCamera();
    }

    btnCapture.addEventListener('click', function () {
        if (recognizing) return;
        var dataUrl = captureFrame();
        if (dataUrl) recognizeImage(dataUrl);
    });

    btnRetake.addEventListener('click', retake);
    btnSwitch.addEventListener('click', switchCamera);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopBarcodeLoop();
        } else if (!recognizing && preview.hidden) {
            scanning = true;
            scheduleBarcodeScan();
        }
    });

    window.addEventListener('beforeunload', function () {
        stopBarcodeLoop();
        stopStream();
    });

    startCamera();
})();
