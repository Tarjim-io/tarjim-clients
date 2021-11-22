// Libraries
import React , { useState, useEffect, createContext } from 'react';
import i18n from 'i18n-js';
import memoize from 'lodash.memoize';
import DOMPurify from 'isomorphic-dompurify';

// Config variables 
import { 
	defaultLocale as locale,  
	translationKeys as defaultTranslationKeys,
	supportedLanguages,
	getTranslationsEndpoint,
	defaultLanguage,
} from './config';

var translationKeys = defaultTranslationKeys;

const LOCALE_UP_TO_DATE = 'locale up to date';

export const LocalizationContext = createContext({
	__T: () => {},
	__TS: () => {},
	__TM: () => {},
	__TI: () => {},
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
	// Emulate force update with react hooks
	const forceUpdate = useForceUpdate(); 
	

	/**
	 * Execute on component mount
	 */
	useEffect(() => {
		// Get language from cake
		let language;
		if ('ReactNative' != navigator.product) {
			let languageElement = document.getElementById('language');
			language = defaultLanguage;
			if (languageElement) {
				language = languageElement.getAttribute('data-language')
			}
		}
		else {
			language = defaultLanguage;
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
	const __T = memoize (
		(key, config) => {
			let tempKey = key;
			if (typeof key === 'object' || Array.isArray(key)) {
				tempKey = key['key'];
			}
			
			let translationValue = getTranslationValue(key);
			let value = translationValue.value;
			let translationId = translationValue.translationId;
			let assignTarjimId = translationValue.assignTarjimId;
			let translation = translationValue.fullValue;
			
			// If type is image call __TM() instead
			if (translation.type && translation.type === 'image') {
				return __TM(key, config);
			}
				
			//if ((typeof key === 'object' || Array.isArray(key)) && value) {
			if (config && !isEmpty(config.mappings) && value) {
				let mappings = config.mappings;
				if (config.subkey) {
					mappings = mappings[config.subkey];
				}
				value = _injectValuesInTranslation(value, mappings);	
			}
			
			let renderAsHtml = false;
			let sanitized;	
			if ('ReactNative' != navigator.product) {
				sanitized = DOMPurify.sanitize(value)

				if (sanitized.match(/<[^>]+>/g)) {
					renderAsHtml = true;
				}
			}
			else {
				return  value;
			}

			if (
				(typeof translation.skip_tid !== 'undefined' && translation.skip_tid === true) ||
				(config && config.skipAssignTid) ||
				(config && config.skipTid)
			) {
				assignTarjimId = false;
				renderAsHtml = false;
			}

			if (assignTarjimId || renderAsHtml) {
				return <span data-tid={translationId} dangerouslySetInnerHTML={{__html: sanitized}}></span>
			}
			else {
				return sanitized;
			}
		}
		,
		(key, config) => (config ? key + JSON.stringify(config) : key)
	);
	 
	/**
	 * Shorthand for __T(key, {skipTid: true})
	 * skip assiging tid and wrapping in span
	 * used for images, placeholder, select options, title...
	 */
	function __TS(key) {
		return __T(key, {skipTid: true});
	}

	/**
	 * Alias for __TM()
	 */
	function __TI(key, attributes) {
		return __TM(key, attributes);
	}

	/**
	 * Used for media
	 * attributes for media eg: class, id, width...
	 * If received key doesn't have type:image return __T(key) instead
	 */
	function __TM(key, attributes={}) {
		let translationValue = getTranslationValue(key);
		let value = translationValue.value;
		let translationId = translationValue.translationId;
		let translation = translationValue.fullValue;

		if (translation.type && translation.type === 'image') {
			let attributesFromRemote = {};

			let src = translation.value;
			translationId = translation.id;
			if (!isEmpty(translation.attributes)) {
				attributesFromRemote = translation.attributes;
			}

			// Merge attributes from tarjim.io and those received from view
			// for attributes that exist in both arrays take the value from tarjim.io
			attributes = {
				...attributes,
				...attributesFromRemote
			}

			let sanitized;
			let response;
			if ('ReactNative' != navigator.product) {
				sanitized = DOMPurify.sanitize(value);
				response = {
					'src': sanitized,
					'data-tid': translationId,
				}
			}
			else {
				sanitized = value;
				response = {
					'source': {
						'uri': sanitized
					}
				}
			}


			for (let [attribute, attributeValue] of Object.entries(attributes)) {
				// Avoid react warnings by changing class to className
				if (attribute === 'class') {
					attribute = 'className';
				}
				response[attribute] = attributeValue;	
			}

			return response;
		}
		else {
			return __T(key);
		}
	}

	/**
	 * Get value for key from translations object 
	 * returns array with
	 * value => string to render or media src
	 * translationId => id to assign to data-tid
	 * assignTarjimId => boolean
	 * fullValue => full object for from $_T to retreive extra attributes if needed
	 */
	function getTranslationValue(key) {
		let tempKey = key;
		if (typeof key === 'object' || Array.isArray(key)) {
			tempKey = key['key'];
		}

		let translation = i18n.t(typeof tempKey == 'string' ? tempKey.toLowerCase() : tempKey, {defaultValue: tempKey})
		let translationString 
		let assignTarjimId = false;
		let translationId
		if (typeof translation === 'object' || Array.isArray(translation)) {
			translationString = translation.value;
			translationId = translation.id;
			assignTarjimId = true;
		}
		else {
			translationString = translation;
		}

		let result = {
			'value': translationString,
			'translationId': translationId,
			'assignTarjimId': assignTarjimId,
			'fullValue': translation
		};

		return result;
	}

	/** 
	 *
	 */
	function _injectValuesInTranslation(translationString, mappings) {
		let regex = /%%.*?%%/g;
		let valuesKeysArray = translationString.match(regex);
		translationString = translationString.replaceAll('%','');
		
		if (!isEmpty(valuesKeysArray)) {
			for (let i = 0; i < valuesKeysArray.length; i++) {
				let valueKeyStripped = valuesKeysArray[i].replaceAll('%','').toLowerCase();
				regex = new RegExp(valueKeyStripped, 'ig')
				translationString = translationString.replace(regex, mappings[valueKeyStripped]); 
			}
		}

		return translationString;
	}

	/**
	 *
	 */
	const isEmpty = (variable) => {

		if (variable === false) {
			return true;
		}

		if (Array.isArray(variable)) {
			return variable.length === 0;
		}

		if (variable === undefined || variable === null) {
			return true;
		}

		if (typeof variable === 'string' && variable.trim() === '') {
			return true;
		}

		if (typeof variable === 'object') {
			return (Object.entries(variable).length === 0 &&
				!(variable instanceof Date));
		}

		return false;
	}

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
		let language;
		if ('ReactNative' != navigator.product) {
			let languageElement = document.getElementById('language');
			language = defaultLanguage;
			if (languageElement) {
				language = languageElement.getAttribute('data-language')
			}
		}
		else {
			language = defaultLanguage;
		}
		
		// Update config
		_setI18nConfig(language);
		forceUpdate();
	}

	/**
	 *
	 */
	async function getTranslationsFromApi() {
		let translations = {};

		try {
			let response = await fetch(getTranslationsEndpoint);
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
				__TS,
				__TM,
				__TI,
        setTranslation: setTranslation,
        getCurrentLocale: getCurrentLocale
			}}>
			{children}
		</LocalizationContext.Provider>
	);
}
