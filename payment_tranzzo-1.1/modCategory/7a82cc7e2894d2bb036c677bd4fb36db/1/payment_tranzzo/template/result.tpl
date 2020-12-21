<link rel="stylesheet" href="/assets/components/payment_tranzzo/css/tranzzo.css">

 {if $status == 'success'}
 <p>Ваш заказ оплачен успешно</p>
 {/if}

 {if $status == 'rejected'}
 <p>При оплате произошла ошибка. Попробуйте еще раз или свяжите с администратором сайта и расскажите о проблеме.</p>
 {/if}