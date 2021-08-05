# Svea checkout settings and instructions
### How to install:
Install it to via composer in your magento site:   
```
composer config repositories.svea_checkout vcs git@github.com:sveawebpay/nwt-magento2-checkout.git   
composer require sveawebpay/nwt-magento2-checkout
```

If you don't want to use composer, install it manually:download and copy all files into app/code/Svea/Checkout/ directory.   
### Enable it in Magento:
` php bin/magento module:enable --clear-static-content Svea_Checkout`   
` php bin/magento setup:upgrade`
## Settings and instructions:
These instructions and guides are for the Svea checkout module to Magento 2.
To activate it as a option follow these steps:
Stores->Configuration->Sales->Payment methods->Svea Checkout.
![Config](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst1.png "Config")
**Step 1:**
To find the settings follow these steps: Store->Configuration or on the left side in the panel.Then you can find the settings under: Svea->Checkout.
![Navigation](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst2.png "Navigation")

**Step 2:**
![Conf2](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst3.png "Conf2")

*Enabled*:  Yes/No, If you want to enable the checkout choose "Yes", if not choose "No".

*Testmode:*  Before you go live it's always good to test the checkout. Choose "Yes" to put the module in test mode so you can check if everything works as it should. When that is done, put the setting as "No" for live mode.

*Checkout key and Secret key*: Merchant identifier, needed for it to work. Svea sends this out when you get registered as a client.

**Step 3:**
![Conf3](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst4.png "Conf3")

*Send order email:* If you’re having issues with double order e-mails being sent set this function to ”no”

*Use reward points:* This is a function for magento commerce only.

*Default Shipping Method:* If you want a specific shipping method to be chosen directly. 

*Default Country:* This is only important if you allow payments from multiple countries. If only 1 country is in use it will automatically choose the one from "Allowed Payment Countries".

*Allowed Payment Countries:* If you allow more than 1 payment country choose which oneshere.

*Capture/Refund payment*: If you want to enable capturing/refunding payments online. I.e when a client creates an invoice, do you want it to be charged at Svea automatically?

*Can capture partial:* This enables/disables the option to make partial invoices or refunds.

**Settings continuation:**
![Conf4](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst5.png "Conf4")

*Subscribe Newsletter* checked by default: When reaching checkout, this decides if the checkbox for subscription to newsletter is marked or not.

*Register Guest Customers*: If you want to register not logged in customers when the order is placed.

*Url for terms page*: This is where you choose what page the "Terms and agreements" link incheckout should lead to.

*Replace checkout url with Svea Checkout:* "Yes/No", "Yes" means that Go to checkout in minicart will lead to Svea.

**Steps 4:**
![Conf5](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst6.png "Conf5")

*Display Newsletter checkbox*: Simple "Yes/No", yes to display the checkbox, no to hide it.

*Display Discount Form:* Simple "Yes/No", yes to display the discount form, no to hide it.

*Display Comment*: If you want to disable the customer comment section in the checkout choose option "No".

*Display link to a different payment method:* If you have another payment option, choose "Yes" to display it.

*Display Additional block*: Simple "Yes/No", yes to display the additional block above checkout, no to hide it.

*Additional block Content*: This is where you choose what to show in the additional block.

**Step 5:**
![Conf6](https://raw.githubusercontent.com/sveawebpay/nwt-magento2-checkout/master/docs/sveainst7.png "Conf6")

*Display Crossell Products:* Simple "Yes/No", Yes to show crossell products.

*Number of products:* The amount of products shown in the slider.
