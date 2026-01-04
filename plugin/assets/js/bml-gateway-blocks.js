/**
 * BML Payment Gateway Block Integration
 */
(function () {
  'use strict';

  // Wait for wc to be available.
  function registerBMLGateway() {
    if (
      typeof window.wc === 'undefined' ||
      typeof window.wc.wcBlocksRegistry === 'undefined' ||
      typeof window.wc.wcSettings === 'undefined' ||
      typeof window.wp === 'undefined' ||
      typeof window.wp.element === 'undefined'
    ) {
      // Try again in 100ms.
      setTimeout(registerBMLGateway, 100);
      return;
    }

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getPaymentMethodData } = window.wc.wcSettings;
    const { createElement } = window.wp.element;

    // Check if the gateway data is available.
    const gatewayData =
      typeof window.bmlGatewayData !== 'undefined'
        ? window.bmlGatewayData
        : null;

    // Don't register if the gateway is disabled.
    if (gatewayData && gatewayData.enabled === false) {
      return;
    }

    /**
     * Content component for the payment method.
     */
    const Content = function () {
      const settings = gatewayData || getPaymentMethodData('bml_gateway') || {};
      return createElement(
        'div',
        null,
        settings.description || 'Pay securely using your BML account.'
      );
    };

    /**
     * Label component for the payment method.
     */
    const Label = function () {
      const settings = gatewayData || getPaymentMethodData('bml_gateway') || {};

      // Include icon if available.
      if (settings.icon) {
        return createElement(
          'span',
          { className: 'wc-block-components-payment-method-label' },
          createElement('img', {
            src: settings.icon,
            alt: settings.title || 'Bank of Maldives',
            style: { display: 'inline-block', maxHeight: '24px', marginRight: '8px', verticalAlign: 'middle' }
          }),
          createElement('span', null, settings.title || 'Bank of Maldives')
        );
      }

      return createElement('span', null, settings.title || 'Bank of Maldives');
    };

    // Register the payment method.
    if (typeof registerPaymentMethod === 'function') {
      // Only register if gateway is enabled.
      var isGatewayEnabled = gatewayData && gatewayData.enabled !== false;

      registerPaymentMethod({
        name: 'bml_gateway',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        ariaLabel: 'Bank of Maldives',
        canMakePayment: function () {
          // Only allow this payment method if it's enabled.
          return isGatewayEnabled;
        },
        supports: {
          products: true,
        },
      });
    }
  }

  // Start registration.
  registerBMLGateway();
})();
