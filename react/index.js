// Libraries
import React , { useState, useEffect, createContext } from 'react';
import i18n from 'i18n-js';
import memoize from 'lodash.memoize';

// Languages
import locale from 'locale/locale.json';

var translationKeys = { en: locale.results.react.en, fr: locale.results.react.fr };

const LOCALE_UP_TO_DATE = 'locale up to date';

export const LocalizationContext = createContext({
	__T: () => {},
	setTranslation: () => {},
  getCurrentLocale: () => {}
});

// Create your forceUpdate hook
function useForceUpdate() {

	// Disable eslint warning for next line
	// eslint-disable-next-line no-unused-vars
	const [value, setValue] = useState(0);
	return () => setValue(value => ++value);
}

export const LocalizationProvider = ({children}) => {
	const forceUpdate = useForceUpdate(); 
	// Emulate force update with react hooks
//	const forceUpdate = useForceUpdate();


	/**
	 * Execute on component mount
	 */
	useEffect(() => {
		// Get language from cake
		let languageElement = document.getElementById('language');
    let language = 'en';
    if (languageElement) {
      language = languageElement.getAttribute('data-language')
    }
		
		// Set initial config
		_setI18nConfig(language);

    // Set language
		async function _updateTranslations() {
			await updateTranslationKeys();
		}
		_updateTranslations();
		forceUpdate();

		// Disable eslint warning for next line
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	/**
	 *
	 */
	const __T = memoize(
		(key, config) => i18n.t(key, {defaultValue: key, ...config}),
		(key, config) => (config ? key + JSON.stringify(config) : key)
	);

	/**
	 *
	 */
	async function setTranslation(languageTag, isRTL = false) {
		// Clear translation cache
		__T.cache.clear();

		// Set translation
		i18n.translations = { [languageTag]: translationKeys[languageTag] };
		i18n.locale = languageTag;

		// Necessary for android
		forceUpdate();
	}

  /**
   *
   */
	async function updateTranslationKeys() {
		let updatedTranslationKeys = await getTranslationsFromApi();
		translationKeys = updatedTranslationKeys;

		// Get language from cake
		let languageElement = document.getElementById('language');
    let language = 'en';
    if (languageElement) {
      language = languageElement.getAttribute('data-language')
    }
		
		// Update config
		_setI18nConfig(language);
		forceUpdate();
	}

	/**
	 *
	 */
	async function getTranslationsFromApi() {
		let supportedLanguages = ['en', 'fr'];
		let translations = { en: {}, fr: {} };

		let localeLastUpdated = locale.meta.results_last_update; 

		try {
			let response = await fetch(`/api/v1/get-latest-frontend-locale?locale_last_updated=${localeLastUpdated}`);
			let result = await response.json();
			if (result.result.data === LOCALE_UP_TO_DATE) {
				translations = translationKeys;	
			}
			else {
				let apiTranslations = result.result.data;
				for (const lang of supportedLanguages) {
					translations[lang] = apiTranslations[lang] 
				}
			}
		} catch(err) {
			console.log('Translations api error: ', err);
			translations = translationKeys;	
		}

		return translations;
	}

	/**
	 *
	 */
	function _setI18nConfig(language = 'en') {
		// Fallback if no available language fits
		const fallback = { languageTag: language };

		// Set best available language as translation
		const { languageTag } = fallback;

		// Clear translation cache
		__T.cache.clear();

		// Set i18n-js config
		i18n.translations = { [languageTag]: translationKeys[languageTag] };
		i18n.locale = languageTag;
	};

  /**
   *
   */
  function getCurrentLocale() {
    return i18n.currentLocale();
  }

	/**
	 * Render
	 */
	return (
		<LocalizationContext.Provider
			value={{
				__T,
        setTranslation: setTranslation,
        getCurrentLocale: getCurrentLocale
			}}>
			{children}
		</LocalizationContext.Provider>
	);
}
