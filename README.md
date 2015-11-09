# giftd
Подарочные карты Giftd

1. Требования к CMS
-------------------

* Версия Joomla 2.5/3.x
* Virtuemart 2.6/3.x
* JoomShopping 3.x/4.x
* HikaShop 2.2+

2. Установите плагин Giftd через Менеджер расширений Joomla
-------------------------------------------------------------
* В административной панели вашего магазина Joomla перейдите в «Менеджер расширений», раздел «Установка».
* Перейдите на вкладку «Установить из URL», введите адрес плагина в нашем репозитории: `https://github.com/Arkadiy-Sedelnikov/giftd/archive/master.zip` и нажмите «Установить».
* После успешной установки вы увидите надпись «Установка плагина успешно завершена».
* Перейдите в раздел «Менеджер расширений» → «Управление». Найдите плагин Giftd с помощью строки поиска, отметьте его галкой и нажмите кнопку «Включить».

3. Настройте плагин
-------------------

* Перейдите в раздел «Расширения» → «Менеджер плагинов». Найдите плагин Giftd с помощью строки поиска и кликните по его названию.
* Укажите в соответствующее поле ID пользователя Giftd (user_id).
* Впишите в соответствующее поле ключ API Giftd (api_key).
* Впишите в соответствующее поле Код партнера Giftd.
* Укажите префикс кодов подарочных карт.

Получить данные для заполнение полей можно по ссылке: https://partner.giftd.ru/partner/apiCredentials

> #####  Убедитесь, что плагины Joomla разрешены в вашем интернет-магазине:

> **VirtueMart**  
> Меню «Компоненты» → «VirtueMart» → «Конфигурация». В разделе «Настройки магазина» должна быть включена опция «Включить плагины Joomla».

> **JoomShopping**  
> Меню «Компоненты» → JoomShopping → «Настройки» → «Товар». Опция «Использовать плагины в описании?» должна быть включена.

> **HikaShop**  
> Ура! Ничего не нужно делать!

* Если у вас магазин на VirtueMart, то вам придется внести одно небольшое изменение в исходный код магазина:

1. Откройте файл `components/com_virtuemart/helpers/cart.php`
2. Найдите функцию `checkoutData()`
3. В конце этой функции **перед** строчкой `if (!empty($this->couponCode)) {` вставьте следующий код:

```php
/* Giftd hack */
if (empty($this->couponCode) && !empty(self::$_cart->cartData["couponCode"])) {
   $this->couponCode = self::$_cart->cartData["couponCode"];
  }
/* End Giftd hack */
```
4. Так же необходмо изменить функцию редиректа:

```php
/* Giftd hack */
if (!empty($redirectMsg)) {
    $this->couponCode = '';
    $this->_inCheckOut = false;
    $this->setCartIntoSession();

//doesn't work the first time
    //return $this->redirecter('index.php?option=com_virtuemart&view=cart'.$layoutName , $redirectMsg);
//its work
     JFactory::getApplication()->redirect('index.php?option=com_virtuemart&view=cart'.$layoutName , $redirectMsg);
   }
/* End Giftd hack */
```

***
