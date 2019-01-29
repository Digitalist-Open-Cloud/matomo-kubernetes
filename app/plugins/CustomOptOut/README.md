# Custom Opt-Out Styles Piwik Plugin

## Description

Adds a new admin tab allowing to change the opt-out CSS Styles for each website.

## Usage

1) Click on the "Settings" link located in the top menu on the right

2) Click on the "Custom Opt-Out" tab located in the "Settings" section of the sidebar on the left

3) Enter your customized CSS code into the textarea input field called "Custom Css" e.g.
```css     
  body {
    font-family: Arial, Verdana, sans-serif;
    font-size: 12px;
    color: #ddd;
    line-height: 160%;
    margin: 10px;
    padding: 0;
  }
```
or insert a URL to the file containing your custom CSS into the input field called "External CSS File" e.g.

  ``http://www.example.org/styles/piwikcustom.css``

4) Click the "Save" button.

5) Use the iframe code provided below the input fields to add the Piwiki Opt-Out to your website.

## How to change the opt-out text

I needed to remove this feature due to the breaking changes in [Piwik 2.11.0](https://github.com/piwik/piwik/blob/master/CHANGELOG.md#piwik-2110)

## Requirements

+ Piwik >=3.0.0-b1

## Authors

**Jens Averkamp**

+ [https://github.com/Zeichen32](https://github.com/Zeichen32)

**Sven Motz**

+ [https://github.com/xMysteriox](https://github.com/xMysteriox)

## Support
**Please direct any feedback to [https://github.com/Zeichen32/PiwikCustomOptOut](https://github.com/Zeichen32/PiwikCustomOptOut)**

## Copyright and license

Released under the GPL v3 (or later) license, see [LICENSE.md](LICENSE.md)
