window.addEventListener("load", function(evt) {
  var woocommerce_omnipay_production_mode = document.getElementById("woocommerce_omnipay_production_mode");
  woocommerce_omnipay_production_mode.addEventListener('change', function(e){
    toggle_custome_url(e.target.value);
  });
  var production_mode_default = woocommerce_omnipay_production_mode.options[woocommerce_omnipay_production_mode.selectedIndex].value;
  toggle_custome_url(production_mode_default)
});

function toggle_custome_url(selected_value ){
  var woocommerce_omnipay_customForm = document.getElementById("woocommerce_omnipay_customForm").closest("tr");
  var woocommerce_omnipay_apiUrl = document.getElementById("woocommerce_omnipay_apiUrl").closest("tr");
  woocommerce_omnipay_customForm.style.display = "table-row";
  woocommerce_omnipay_apiUrl.style.display = "table-row";
  if(selected_value === 'prod'){
    woocommerce_omnipay_customForm.style.display = "none";
    woocommerce_omnipay_apiUrl.style.display = "none";
  }
}