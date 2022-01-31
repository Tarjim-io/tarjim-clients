// Libraries
import React , { useState, useEffect, createContext } from 'react';
import memoize from 'lodash.memoize';
import DOMPurify from 'isomorphic-dompurify';
import cachedTarjimData from './cache/cachedTarjimData';

// Config variables 
import { 
	supportedLanguages,
	defaultLanguage,
	defaultNamespace,
	additionalNamespaces,
	tarjimApikey,
	projectId,
} from './config';

export const LocalizationContext = createContext({
	__T: () => {},
	__TS: () => {},
	__TM: () => {},
	__TI: () => {},
	setTranslation: () => {},
  getCurrentLocale: () => {}
});

var allNamespaces = additionalNamespaces;
allNamespaces.unshift(defaultNamespace);

export const LocalizationProvider = ({children}) => {

	var translationKeys = {};

	const LOCALE_UP_TO_DATE = 'locale up to date';

	const getMetaEndpoint = `https://tarjim.io/api/v1/translationkeys/json/meta/${projectId}?apikey=${tarjimApikey}`;
	const getTranslationsEndpoint = `https://tarjim.io/api/v1/translationkeys/jsonByNameSpaces`;

	var localeLastUpdated = 0;
	if (cachedTarjimData.hasOwnProperty('meta') && cachedTarjimData.meta.hasOwnProperty('results_last_update')) {
		localeLastUpdated = cachedTarjimData.meta.results_last_update;
	}
	
	var cachedTranslations = {};
	if (cachedTarjimData.hasOwnProperty('results')) {
		cachedTranslations = cachedTarjimData.results;
	}


	const [ translations, setTranslations ] = useState(translationKeys);
	const [ currentLocale, setCurrentLocale ] = useState(defaultLanguage);

	/**
	 * Execute on component mount
	 */
	useEffect(() => {
		loadInitialTranslations();
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
	//	_setTarjimConfig(language);

    // Set language
		async function _updateTranslations() {
			await updateTranslationKeys();
		}
		_updateTranslations();

		// Disable eslint warning for next line
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	/**
	 *
	 */
	function loadInitialTranslations() {
		allNamespaces.forEach(namespace => {
			translationKeys[namespace] = {};
			supportedLanguages.forEach(language => {
				if (cachedTranslations.hasOwnProperty(namespace)) {
					if (cachedTranslations[namespace].hasOwnProperty(language)) {
						translationKeys[namespace][language] = cachedTranslations[namespace][language]; 
					}
					else {
						translationKeys[namespace][language] = {}; 
					}
				}
				else {
					translationKeys[namespace][language] = {};
					//_translationKeys[namespace][language] = {}; 
				}
			})
		});
	}

	/**
	 *
	 */
	const __T = memoize (
		(key, config) => {
			let namespace = defaultNamespace;
			if (config && config.namespace) {
				namespace = config.namespace;
			}

			let tempKey = key;
			if (typeof key === 'object' || Array.isArray(key)) {
				tempKey = key['key'];
			}
			
			let translationValue = getTranslationValue(key, namespace);
			let value = translationValue.value;
			let translationId = translationValue.translationId;
			let assignTarjimId = translationValue.assignTarjimId;
			let translation = translationValue.fullValue;

			// If type is image call __TM() instead
	//		if (translation.type && translation.type === 'image') {
	//			return __TM(key, config);
	//		}
				
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
	function __TS(key, config = {}) {
		config = {
			...config,
			skipTid: true,
		};
		return __T(key, config);
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
		let namespace = defaultNamespace;

		if (attributes && attributes.namespace) {
			namespace = attributes.namespace;
		}

		let translationValue = getTranslationValue(key, namespace);
		let value = translationValue.value;
		let translationId = translationValue.translationId;
		let translation = translationValue.fullValue;

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

		if (attributes && attributes.namespace) {
			delete attributes.namespace;
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

	/**
	 * Get value for key from translations object 
	 * returns array with
	 * value => string to render or media src
	 * translationId => id to assign to data-tid
	 * assignTarjimId => boolean
	 * fullValue => full object for from $_T to retreive extra attributes if needed
	 */
	function getTranslationValue(key, namespace) {
		if (isEmpty(translationKeys)) {
			loadInitialTranslations();
		}

		let tempKey = key;
		if (typeof key === 'object' || Array.isArray(key)) {
			tempKey = key['key'];
		}

		let translation;
		if (
			translations.hasOwnProperty(namespace) && 
			translations[namespace][currentLocale].hasOwnProperty(tempKey.toLowerCase())
		) {
			translation = translations[namespace][currentLocale][tempKey.toLowerCase()];
		}
		else {
			translation = tempKey;
		}

		let translationString;
		let assignTarjimId = false;
		let translationId;
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

		let percentRegex = new RegExp('%%', 'mg')
		translationString = translationString.replace(percentRegex,'');
		
		if (!isEmpty(valuesKeysArray)) {
			for (let i = 0; i < valuesKeysArray.length; i++) {
				let valueKeyStripped = valuesKeysArray[i].replace(percentRegex,'').toLowerCase();

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
		// Set translation
		setCurrentLocale(languageTag);
	}

  /**
   *
   */
	async function updateTranslationKeys() {
		if (await translationsNeedUpdate()) {
			let updatedTranslationKeys = await getTranslationsFromApi();
			translationKeys = updatedTranslationKeys;
		}


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
		_setTarjimConfig(language);
	}

	async function translationsNeedUpdate() {
		let returnValue;
		try {
			let response = await fetch(getMetaEndpoint);
			let result = await response.json();
			let apiLastUpdated = result.result.data.meta.results_last_update;
			if (localeLastUpdated < apiLastUpdated) {
				returnValue = true;
			}
			else {
				returnValue = false;
			}
		} catch(err) {
			console.log('Translations api error: ', err);
			returnValue = true;
		}

		return returnValue;
	}

	/**
	 *
	 */
	async function getTranslationsFromApi() {
		let _translations = {};

		try {
			let response = await fetch(getTranslationsEndpoint, {
				method: 'POST',
				body: JSON.stringify({
					'project_id': projectId,
					'namespaces': allNamespaces,
					'apikey': tarjimApikey,
				}),
			});
			let result = await response.json();
			if (result.hasOwnProperty('result')){
				result = result.result.data
			}
			let apiTranslations = result.results;
			_translations = apiTranslations;
		} catch(err) {
			console.log('Translations api error: ', err);
			_translations = translationKeys;	
		}

		return _translations;
	}

	/**
	 *
	 */
	function _setTarjimConfig(language = 'en') {
		setCurrentLocale(language);
		setTranslations(translationKeys);
	};

  /**
   *
   */
  function getCurrentLocale() {
    return currentLocale;
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
