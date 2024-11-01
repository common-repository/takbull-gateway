const bit_settings = window.wc.wcSettings.getSetting( 'takbull_bit', {} );
const bit_label = window.wp.htmlEntities.decodeEntities( bit_settings.title );
const Bit_Content = () => {
    return window.wp.htmlEntities.decodeEntities( bit_settings.description || '' );
};
const Bit_Block_Gateway = {
    name: 'takbull_bit',
    label: bit_label,
    content: Object( window.wp.element.createElement )( Bit_Content, null ),
    edit: Object( window.wp.element.createElement )( Bit_Content, null ),
    canMakePayment: () => true,
    ariaLabel: bit_label,
    supports: {
        features: bit_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Bit_Block_Gateway );