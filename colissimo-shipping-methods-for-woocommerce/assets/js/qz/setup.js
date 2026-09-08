/**
 * Shared QZ Tray bootstrap for the admin: promise type and, when a certificate is configured,
 * request signing.
 *
 * QZ Tray only offers "Remember this decision" on a signed request, so an unsigned setup shows the
 * "Untrusted website" popup on every connection and every print.
 */
window.lpcQz = (function ($) {
    let ready = false;

    function signingConfig() {
        return 'undefined' === typeof lpcQzSigning ? {} : lpcQzSigning;
    }

    function isSigned() {
        const config = signingConfig();

        return !!(config.certificate && config.signUrl);
    }

    function isAvailable() {
        return 'undefined' !== typeof qz && !!qz.websocket;
    }

    function setup() {
        if (ready) {
            return;
        }
        ready = true;

        qz.api.setPromiseType(function (resolver) {
            return new Promise(resolver);
        });

        if (!isSigned()) {
            // No certificate configured yet: QZ Tray asks the operator to allow each call
            return;
        }

        const config = signingConfig();

        qz.security.setCertificatePromise(function (resolve) {
            resolve(config.certificate);
        });

        qz.security.setSignatureAlgorithm(config.algorithm || 'SHA512');

        // QZ Tray hands over the payload to sign, the private key never leaves the server
        qz.security.setSignaturePromise(function (toSign) {
            return function (resolve, reject) {
                $.ajax({
                    type: 'POST',
                    url: config.signUrl,
                    data: {request: toSign},
                    dataType: 'json'
                }).done(function (response) {
                    if (response && 'success' === response.type && response.signature) {
                        resolve(response.signature);
                        return;
                    }

                    reject(new Error(response && response.message ? response.message : 'QZ Tray signing failed'));
                }).fail(function (xhr, status, error) {
                    reject(new Error(error || status));
                });
            };
        });
    }

    function connect() {
        setup();

        if (qz.websocket.isActive()) {
            return Promise.resolve();
        }

        return qz.websocket.connect();
    }

    function disconnect() {
        if (qz.websocket.isActive()) {
            qz.websocket.disconnect();
        }
    }

    return {
        isAvailable: isAvailable,
        isSigned: isSigned,
        setup: setup,
        connect: connect,
        disconnect: disconnect
    };
})(jQuery);
