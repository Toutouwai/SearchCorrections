# Search Corrections

Suggests corrected spellings for search terms based on words that exist in the website.

## Method

The module has a single method intended for public use: `SearchCorrections::findSimilarWords($target, $selector, $fields, $options)`

It creates a list of unique words (the "word list") that exist on the pages and fields that you define, and compares those words to a target word that you give it. The method returns an array of words that are sufficiently similar to the target word.

### Similarity

The method ranks similar words by calculating the [Levenshtein distance](https://en.wikipedia.org/wiki/Levenshtein_distance) from the target word.

Where several results have the same Levenshtein distance from the target word these are ordered so that results which have more letters in common with the target word at the start of the result word are higher in the order.

### Method arguments

`$target` `(string)` The input word that may have spelling mistakes.

`$selector` `(string)` A selector string to find the pages that the word list will be derived from.

`$fields` `(array)` An array of field names that the word list will be derived from.

`$options` `(array)` Optional: an array of options as described below.
* `minWordLength` `(int)` Words below this length will not be included in the word list. Default: `4`
* `lengthRange` `(int)` Words that are longer or shorter than the target word by more than this number will not be included in the word list. Default: `2`
* `expire` `(int)` The word list is cached for this number of seconds, to improve performance. Default: `3600`
* `maxChangePercent` `(int)` When the Levenshtein distance between a word and the target word is calculated, the distance is then converted into a percentage of changed letters relative to the target word. Words that have a higher percentage change than this value are not included in the results. Default: `50`
* `insertionCost` `(int)` This is an optional argument for the PHP `levenshtein()` function. See the [docs](https://www.php.net/manual/en/function.levenshtein.php) for details. Default: `1`
* `replacementCost` `(int)` This is an optional argument for the PHP `levenshtein()` function. See the [docs](https://www.php.net/manual/en/function.levenshtein.php) for details. Default: `1`
* `deletionCost` `(int)` This is an optional argument for the PHP `levenshtein()` function. See the [docs](https://www.php.net/manual/en/function.levenshtein.php) for details. Default: `1`


## Example of use

```php
// The input word that may need correcting
$target = 'dispraxia';

// Get the Search Corrections module
$sc = $modules->get('SearchCorrections');
// Define a selector string to find the pages that the word list will be derived from
$selector = "template=basic-page";
// Define an array of field names that the word list will be derived from
$flds = ['title', 'body'];
// Optional: override any of the default options
$options = ['maxChangePercent' => 55];

// Get an array of similar words that exist in the pages/fields you defined
// The return value is in the format $word => $levenshtein_distance
$results = $sc->findSimilarWords($target, $selector, $flds, $options);
```

Example result:

![sc-result](https://github.com/Toutouwai/SearchCorrections/assets/1538852/ff15d5de-b673-49b3-9153-f1d92daef527)
