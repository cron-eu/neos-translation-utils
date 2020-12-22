#neos-translation-utils

NodeType translations in Neos
----

As can be read [here](https://docs.neos.io/cms/manual/content-repository/nodetype-translations), Neos supports the
translation of elements in NodeTypes.

A NodeType `Configuration/NodeTypes.Content.YourContentNodeTypeName.yaml` can look like this:

```
'Vendor.Site:Content.YourContentNodeTypeName':
  ui:
    help:
      message: 'i18n'
    inspector:
      tabs:
        yourTab:
          label: 'i18n'
      groups:
        yourGroup:
          label: 'i18n'
  properties:
    yourProperty:
      type: string
        ui:
          label: 'i18n'
          help:
            message: 'i18n'
```

with `'i18n'` being a "magic value" for Neos to look into the translation files for the translation of the required language.

Neos will expect the respective XLIFF translation for example for the language `en`
in
`Resources/Private/Translations/en/NodeTypes/Content/YourContentNodeTypeName.xlf`.

This follows the [NodeType best practices](https://docs.neos.io/cms/manual/best-practices) and in addition this package
assumes that the NodeType file name represents the namespace of the contained NodeType and not
"... and the file-name MUST represent the namespace of the contained NodeType/s. ..."

Motivation and functionality
----
Adding all the XLIFF files for every NodeType and especially adding and updating all the translation IDs in those files
can become very tedious and time-consuming.

This package adds two simple commands that automatically update the files and their translation IDs.

To do this, it first parses all the NodeTypes and searches for the keys that have the magic value `i18n`.
Out of the file names and these keys, now called translation IDs, it creates the folder structure and the XLIFF files.
Within the XLIFF files it will create the <trans-unit>'s for the translation IDs, considering the already existing translations.
<trans-unit>'s that have no translation ID within the NodeType will be thrown away though.

As one language should be considered the "source"-language, this tool will always update the source-language first.
If this tool is used with a target-language, it will adapt the translations from the source language as &lt;source&gt;-tags in the XLIFF file of the target language.
As only the &lt;target&gt;-tag gets utilized in a target-language, this only helps visually.

To better recognize missing labels when viewing pages in the backend, missing translations will have the value `#<locale>/<file path>:<translation-id>`.

Usage
----
To update both the source and the target language:

`./flow translationutils:update <package-key> <source-language> <target-language>`

or to update only the source language:

`./flow translationutils:updateSource <package-key> <source-language>`
