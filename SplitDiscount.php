<?php

namespace Towersystems\SplitDiscount\Model;

class SplitDiscount extends \Magento\Framework\Model\AbstractModel {

    protected $checkoutSession;
    protected $ruleCollection;
    protected $priceHelper;
    protected $cart;
    protected $totalsCollector;
    
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\SalesRule\Model\RuleFactory $ruleCollection,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector)
    {
        $this->checkoutSession = $checkoutSession;
        $this->ruleCollection = $ruleCollection;
        $this->priceHelper = $priceHelper;
        $this->cart = $cart;
        $this->totalsCollector = $totalsCollector;
    }
    
    public function getSplitDiscounts()
    {
        $this->checkoutSession->unsMyDiscountData();

        $quote = $this->cart->getQuote();
        $this->totalsCollector->collect($quote);

        $discountData = [];
        $discountData = $this->checkoutSession->getMyDiscountData();
        $return = [];
        if (is_array($discountData)) {
            $breakdown = [];
            $shippingDiscount = $discountData["shipping"];
            foreach ($discountData as $itemId => $values) {
                if ($itemId != 'shipping') {
                    foreach ($values as $ruleId => $discData) {
                        if ($discData->getOriginalAmount() > 0) {
                            if (array_key_exists($ruleId, $breakdown)) {
                                $breakdown[$ruleId] += $discData->getOriginalAmount();
                            } else {
                                $breakdown[$ruleId] = $discData->getOriginalAmount();
                            }
                        }
                    }
                }
            }

            $i = 0;
            foreach ($breakdown as $ruleId => $totalRow) {
                $rule = $this->ruleCollection->create()->load($ruleId);
                $return[$i]['title'] = $rule->getStoreLabel();
                $return[$i]['amount'] = "-" . $this->priceHelper->currency(round($totalRow, 2), true, false);
                $i++;
            }

            if ($shippingDiscount) {
                $return[$i]['title'] = "Shipping Discount";
                $return[$i]['amount'] = "-" . $this->priceHelper->currency(round($shippingDiscount, 2), true, false);
            }
        }

        $saveData = json_encode($return);
        $quote->setAppliedDiscounts($saveData)->save();

        return $return;
    }

}