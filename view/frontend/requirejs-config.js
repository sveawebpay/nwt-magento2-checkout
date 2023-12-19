var config = {
    map: {
        '*': {
            sveaCheckout: 'Svea_Checkout/js/checkout',
            sveaShippingMethod: 'Svea_Checkout/js/sveashippingmethod',
            sveaProductCampaign: 'Svea_Checkout/js/product-campaign',
            'Magento_Reward/js/action/set-use-reward-points': 'Svea_Checkout/js/action/set-use-reward-points',
            recurringFormHandler: 'Svea_Checkout/js/recurring-form-handler',
            cancelSubscriptionFormHandler: 'Svea_Checkout/js/cancel-subscription-form-handler',
            internationalFormHandler: 'Svea_Checkout/js/international-form-handler',
        }
    },
    paths: {
        slick: 'Svea_Checkout/js/lib/slick.min'
    },
    shim: {
        slick: {
            deps: ['jquery']
        }
    }
};
