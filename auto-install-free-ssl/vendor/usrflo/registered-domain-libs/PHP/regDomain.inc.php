<?php

/*
 * Calculate the effective registered domain of a fully qualified domain name.
 *
 * <@LICENSE>
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at:
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * </@LICENSE>
 *
 * Florian Sager, 25.07.2008, sager@agitos.de, http://www.agitos.de
 */

/*
 * Remove subdomains from a signing domain to get the registered domain.
 *
 * dkim-reputation.org blocks signing domains on the level of registered domains
 * to rate senders who use e.g. a.spamdomain.tld, b.spamdomain.tld, ... under
 * the most common identifier - the registered domain - finally.
 *
 * This function returns NULL if $signingDomain is TLD itself
 * 
 * $signingDomain has to be provided lowercase (!)
 */

/*
 * Forked: Used namespace and renamed the functions by Anindya Sundar Mandal to fix a bug where a fatal error would be thrown in combination with plugin that uses this library.
 */

use aifs\regDomain;

/* pull in class */
require_once(dirname(__FILE__) . '/regDomain.class.php');

/**
 * Return a single regDomain instance per request (avoids reloading effectiveTLDs.inc.php).
 *
 * @return regDomain
 */
function aifs_reg_domain_object() {
	static $regDomainObj = null;

	if ( null === $regDomainObj ) {
		$regDomainObj = new regDomain();
	}

	return $regDomainObj;
}

/**
 *
 * @param $signingDomain
 * @param bool $fallback
 *
 * @return string|null
 */
function aifs_getRegisteredDomain($signingDomain, $fallback = TRUE) {
	return aifs_reg_domain_object()->getRegisteredDomain($signingDomain, $fallback);
}

function aifs_validDomainPart($domPart) {
	return aifs_reg_domain_object()->validDomainPart($domPart);
}

// recursive helper method
function aifs_findRegisteredDomain($remainingSigningDomainParts, &$treeNode) {
	return aifs_reg_domain_object()->findRegisteredDomain($remainingSigningDomainParts, $treeNode);
}
