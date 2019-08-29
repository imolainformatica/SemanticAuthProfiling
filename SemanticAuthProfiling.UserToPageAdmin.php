<?php

/**
 * CustomPermission extension - 
 *
 * @file
 * @ingroup Extensions
 * @author Gabriele Morlini,Giacomo Lorenzo
 * @copyright � 2019 Imola Informatica
 * @license Imola Informatica
 */
//parses a wiki query and returns a string
 function parseWiki($out, $wikiquery) {
		$out->addWikiText($wikiquery);
		$noParagraphs = str_ireplace("<p>", "", $out->mBodytext);
		$noParagraphs = str_ireplace("</p>", "", $noParagraphs);
		$out->mBodytext='';
		
		return $noParagraphs;
 }
function fromUserToPageAdmin($username){

	$out = new OutputPage();
	if(!empty($username)){
	wfDebug( 'SelectiveActionPageAdmin - '. __METHOD__ . ": utente:".$username." \n", 'log' );
	//da Username->persona wiki [[Matricola aziendale::<matricola>]
	$askUtente = 	"{{#ask:[[Personal mailbox::".$username."]]|format=array |link=none |headers=hide|searchlabel=..|sep={{!}}|titles=show|hidegaps=none|recordsep=<RCRD>|headersep=}}";
	$pageUser = parseWiki($out, $askUtente);
	wfDebug( 'SelectiveActionPageAdmin - '. __METHOD__ . ": utente:".$username."->".$pageUser." \n", 'log' );
	if(!empty($pageUser)){
		//da persona->pagine [[Has owner::<pagina persona>]]
		$ask = 	"{{#ask:[[Has page admin::".$pageUser ."]]|format=array |link=none |headers=hide|searchlabel=..|sep={{!}}|titles=show|hidegaps=none|recordsep=<RCRD>|headersep=}}";
		$pageAdminOwner = parseWiki($out, $ask);
		wfDebug( 'SelectiveActionPageAdmin - '. __METHOD__ . ": Page owner di: ".$pageAdminOwner."\n", 'log' );
		
		$ask = 	"{{#ask:[[Has page admin unit:".$pageUser ."]]|format=array |link=none |headers=hide|searchlabel=..|sep={{!}}|titles=show|hidegaps=none|recordsep=<RCRD>|headersep=}}";
		$uoHeads = parseWiki($out, $ask);
		wfDebug( 'SelectiveActionPageAdmin - '. __METHOD__ . ": uo resp di: ".$uoHeads."\n", 'log' );
		if(!empty($uoHeads)){
			//se persone responsabile più unità organizzative? risultato ha già separatori "|"
			//Has unit owner
		    $ask = 	"{{#ask:[[Has page admin unit::".$pageUser ."]]|format=array |link=none |headers=hide|searchlabel=..|sep={{!}}|titles=show|hidegaps=none|recordsep=<RCRD>|headersep=}}";
			$pageAdminUnitOwner = parseWiki($out, $ask);
			wfDebug( 'SelectiveActionPageAdmin - '. __METHOD__ . ": uo owner:".$pageAdminUnitOwner . "\n", 'log' );
			//da qui in poi standard
			return $pageAdminOwner. "|".$pageAdminUnitOwner;
		}else{
			return $pageAdminOwner ;
		}
	
	}
	else{
		wfWarn('SelectiveAction - '. __METHOD__ .'impossibile determinare pagina utente per '.$username,'log');
		return "";
	}
}
}
