<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$pagetitle = "Terms of Use - ".AppSettings::getParam('site_name');

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<div class="panel panel-default" style="margin-top: 15px;">
		<div class="panel-heading"><div class="panel-title"><?php echo strtoupper(AppSettings::nodeLegalEntity()); ?> TERMS OF USE</div></div>
		<div class="panel-body">
		
		<p class="c0"><span class="c1">Oct 4, 2023</span></p><p class="c0"><span class="c1">&nbsp;</span></p><p class="c0"><span class="c2">Welcome to the </span><span class="c2"><?php echo AppSettings::nodeLegalEntity(); ?></span><span class="c2">&nbsp;Terms of Use agreement. For purposes of this agreement, &ldquo;Site&rdquo; refers to the Company&rsquo;s website, which can be accessed at </span><span class="c2"><?php echo AppSettings::getParam('use_https') ? 'https' : 'http' ;?>://<?php echo AppSettings::getParam('site_domain'); ?>. </span><span class="c2">&ldquo;Service&rdquo; refers to the Company&rsquo;s services accessed via the Site, in which users can</span><span class="c2">&nbsp;buy and sell cryptocurrencies that use the PolyCash protocol, and hold positions in games that use the PolyCash protocol</span><span class="c1">. The terms &ldquo;we,&rdquo; &ldquo;us,&rdquo; and &ldquo;our&rdquo; refer to the Company. &ldquo;You&rdquo; refers to you, as a user of our Site or our Service. </span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">The following Terms of Use apply when you view or use the Service </span><span class="c1">via our website located at https://poly.cash. &nbsp;</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">Please review the following terms carefully. By accessing or using the Service, you signify your agreement to these Terms of Use.</span><span class="c6">&nbsp;If you do not agree to be bound by these Terms of Use in their entirety, you may not access or use the Service</span><span class="c1">. &nbsp;</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">PRIVACY POLICY</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">The Company respects the privacy of its Service users. Please refer to the Company&rsquo;s Privacy Policy (found here: </span><span class="c2"><a href="/privacy-policy" target="_blank">Privacy Policy</a></span><span class="c1">) which explains how we collect, use, and disclose information that pertains to your privacy. When you access or use the Service, you signify your agreement to the Privacy Policy as well as these Terms of Use.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">ABOUT THE SERVICE</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">The Service allows you to </span><span class="c2">buy and sell cryptocurrencies that use the PolyCash protocol, and hold positions in games that use the PolyCash protocol</span><span class="c1">.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">REGISTRATION; RULES FOR USER CONDUCT AND USE OF THE SERVICE</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">You need to be at least </span><span class="c2">18 years old</span><span class="c1">&nbsp;to use the Service.</span></p><p class="c0"><span class="c1">If you are a user who signs up for the Service, you will create a personalized account which includes a unique username and a password to access the Service and to receive messages from the Company. You agree to notify us immediately of any unauthorized use of your password and/or account. The Company will not be responsible for any liabilities, losses, or damages arising out of the unauthorized use of your member name, password and/or account.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">USE RESTRICTIONS</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">Your permission to use the Site is conditioned upon the following use, posting and conduct restrictions: </span></p><p class="c0"><span class="c1">You agree that you will not under any circumstances:</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;access the Service for any reason other than your personal, non-commercial use solely as permitted by the normal functionality of the Service,</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;collect or harvest any personal data of any user of the Site or the Service </span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;use the Site or the Service for the solicitation of business in the course of trade or in connection with a commercial enterprise;</span></p><p class="c0"><span class="c2">&middot; &nbsp; &nbsp;distribute any part or parts of the Site or the Service without our explicit written permission </span><span class="c2">(we grant the operators of public search engines permission to use spiders to copy materials from the site for the sole purpose of creating publicly-available searchable indices but retain the right to revoke this permission at any time on a general or specific basis)</span><span class="c1">;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;use the Service for any unlawful purpose or for the promotion of illegal activities;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;attempt to, or harass, abuse or harm another person or group;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;use another user&rsquo;s account without permission;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;intentionally allow another user to access your account; </span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;provide false or inaccurate information when registering an account;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;interfere or attempt to interfere with the proper functioning of the Service;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;make any automated use of the Site, the Service or the related systems, or take any action that we deem to impose or to potentially impose an unreasonable or disproportionately large load on our servers or network infrastructure;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;bypass any robot exclusion headers or other measures we take to restrict access to the Service, or use any software, technology, or device to scrape, spider, or crawl the Service or harvest or manipulate data; </span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;circumvent, disable or otherwise interfere with any security-related features of the Service or features that prevent or restrict use or copying of content, or enforce limitations on use of the Service or the content accessible via the Service; or </span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;publish or link to malicious content of any sort, including that intended to damage or disrupt another user&rsquo;s browser or computer.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">POSTING AND CONDUCT RESTRICTIONS</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">When you create your own personalized </span><span class="c2">account</span><span class="c2">, you may be able to provide </span><span class="c2">images and textual information</span><span class="c1">&nbsp;(&ldquo;User Content&rdquo;) to the Service. You are solely responsible for the User Content that you post, upload, link to or otherwise make available via the Service. </span></p><p class="c0"><span class="c1">&nbsp;You agree that we are only acting as a passive conduit for your online distribution and publication of your User Content. The Company, however, reserves the right to remove any User Content from the Service at its sole discretion.</span></p><p class="c0"><span class="c1">We grant you permission to use and access the Service, subject to the following express conditions surrounding User Content. You agree that failure to adhere to any of these conditions constitutes a material breach of these Terms. </span></p><p class="c0"><span class="c1">By transmitting and submitting any User Content while using the Service, you agree as follows:</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;You are solely responsible for your account and the activity that occurs while signed in to or while using your account;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;You will not post information that is malicious, libelous, false or inaccurate;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;You will not post any information that is abusive, threatening, obscene, defamatory, libelous, or racially, sexually, religiously, or otherwise objectionable and offensive;</span></p><p class="c0"><span class="c2">&middot; &nbsp; &nbsp;You retain all ownership rights in your User Content but you are required to grant the following rights to the Site and to users of the Service as set forth more fully under the &ldquo;License Grant&rdquo; and &ldquo;Intellectual Property&rdquo; provisions below: When you upload or post User Content to the Site or the Service, you grant to the Site a worldwide, non-exclusive, royalty-free, transferable license to use, reproduce, distribute, prepare derivative works of, display, and perform that Content in connection with the provision of the Service; and you grant to each user of the Service, a worldwide, non-exclusive, royalty-free license to access your User Content through the Service, and to use, reproduce, distribute, prepare derivative works of, display and perform such Content to the extent permitted by the Service and under these Terms of Use;</span></p><p class="c0"><span class="c1">&middot; &nbsp; &nbsp;You will not submit content that is copyrighted or subject to third party proprietary rights, including privacy, publicity, trade secret, or others, unless you are the owner of such rights or have the appropriate permission from their rightful owner to specifically submit such content; and</span></p><p class="c0"><span class="c2">&middot; &nbsp; &nbsp;You hereby agree that we have the right to determine whether your User Content submissions are appropriate and comply with these Terms of Service, remove any and/or all of your submissions, and terminate your account with or without prior notice.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">You understand and agree that any liability, loss or damage that occurs as a result of the use of any User Content that you make available or access through your use of the Service is solely your responsibility. The Site is not responsible for any public display or misuse of your User Content. </span></p><p class="c0"><span class="c1">The Site does not, and cannot, pre-screen or monitor all User Content. However, at our discretion, we, or technology we employ, may monitor and/or record your interactions with the Service or with other Users.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">ONLINE CONTENT DISCLAIMER</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">Opinions, advice, statements, offers, or other information or content made available through the Service, but not directly by the Site, are those of their respective authors, and should not necessarily be relied upon. Such authors are solely responsible for such content. </span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">We do not guarantee the accuracy, completeness, or usefulness of any information on the Site or the Service nor do we adopt nor endorse, nor are we responsible for, the accuracy or reliability of any opinion, advice, or statement made by other parties. We take no responsibility and assume no liability for any User Content that you or any other user or third party posts or sends via the Service. </span><span class="c2">Under no circumstances will we be responsible for any loss or damage resulting from anyone&rsquo;s reliance on information or other content posted on the Service, or transmitted to users.</span></p><p class="c0"><span class="c2">Though we strive to enforce these Terms of Use, you may be exposed to User Content that is inaccurate or objectionable when you use or access the Site or the Service. We reserve the right, but have no obligation, to monitor the materials posted in the public areas of the Site or the Service or to limit or deny a user&rsquo;s access to the Service or take other appropriate action if a user violates these Terms of Use or engages in any activity that violates the rights of any person or entity or which we deem unlawful, offensive, abusive, harmful or malicious.</span><span class="c2">&nbsp;E-mails sent between you and other participants that are not readily accessible to the general public will be treated by us as private to the extent required by applicable law. </span><span class="c2">The Company shall have the right to remove any material that in its sole opinion violates, or is alleged to violate, the law or this agreement or which might be offensive, or that might violate the rights, harm, or threaten the safety of users or others. &nbsp;Unauthorized use may result in criminal and/or civil prosecution under Federal, State and local law. If you become aware of a misuse of our Service or violation of these Terms of Use, please contact us at </span><span class="c2"><?php echo AppSettings::nodeContactEmail(); ?></span><span class="c1">.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">LINKS TO OTHER SITES AND/OR MATERIALS</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">As part of the Service, we may provide you with convenient links to third party website(s) (&ldquo;Third Party Sites&rdquo;) as well as content or items belonging to or originating from third parties (the &ldquo;Third Party Applications, Software or Content&rdquo;). These links are provided as a courtesy to Service subscribers. We have no control over Third Party Sites or Third Party Applications, Software or Content or the promotions, materials, information, goods or services available on these Third Party Sites or Third Party Applications, Software or Content. Such Third Party Sites and Third Party Applications, Software or Content are not investigated, monitored or checked for accuracy, appropriateness, or completeness, and we are not responsible for any Third Party Sites accessed through the Site or any Third Party Applications, Software or Content posted on, available through or installed from the Site, including the content, accuracy, offensiveness, opinions, reliability, privacy practices or other policies of or contained in the Third Party Sites or the Third Party Applications, Software or Content. Inclusion of, linking to or permitting the use or installation of any Third Party Site or any Third Party Applications, Software or Content does not imply our approval or endorsement. If you decide to leave the Site and access the Third Party Sites or to use or install any Third Party Applications, Software or Content, you do so at your own risk and you should be aware that our terms and policies, including these Terms of Use, no longer govern. You should review the applicable terms and policies, including privacy and data gathering practices, of any Third Party Site to which you navigate from the Site or relating to any applications you use or install from the Third Party Site.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">COPYRIGHT COMPLAINTS AND COPYRIGHT AGENT</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">(a) Termination of Repeat Infringer Accounts. We respect the intellectual property rights of others and requires that the users do the same. Pursuant to 17 U.S.C. 512(i) of the United States Copyright Act, we have adopted and implemented a policy that provides for the termination in appropriate circumstances of users of the Service who are repeat infringers. We may terminate access for participants or users who are found repeatedly to provide or post protected third party content without necessary rights and permissions.</span></p><p class="c0"><span class="c2">(b) DMCA Take-Down Notices. &nbsp;If you are a copyright owner or an agent thereof and believe, in good faith, that any materials provided on the Service infringe upon your copyrights, you may submit a notification pursuant to the Digital Millennium Copyright Act (</span><span class="c2 c11">see</span><span class="c1">&nbsp;17 U.S.C 512) (&ldquo;DMCA&rdquo;) by sending the following information in writing.</span></p><p class="c0"><span class="c1">&nbsp;</span></p><p class="c0 c3"><span class="c2">1.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">The date of your notification;</span></p><p class="c0 c3"><span class="c2">2.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A physical or electronic signature of a person authorized to act on behalf of the owner of an exclusive right that is allegedly infringed;</span></p><p class="c0 c3"><span class="c2">3.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A description of the copyrighted work claimed to have been infringed, or, if multiple copyrighted works at a single online site are covered by a single notification, a representative list of such works at that site;</span></p><p class="c0 c3"><span class="c2">4.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A description of the material that is claimed to be infringing or to be the subject of infringing activity and information sufficient to enable us to locate such work;</span></p><p class="c0 c3"><span class="c2">5.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">Information reasonably sufficient to permit the service provider to contact you, such as an address, telephone number, and/or email address;</span></p><p class="c0 c3"><span class="c2">6.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A statement that you have a good faith belief that use of the material in the manner complained of is not authorized by the copyright owner, its agent, or the law; and</span></p><p class="c0 c3"><span class="c2">7.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A statement that the information in the notification is accurate, and under penalty of perjury, that you are authorized to act on behalf of the owner of an exclusive right that is allegedly infringed.</span></p><p class="c0"><span class="c1">(c) Counter-Notices. If you believe that your User Content that has been removed from the Site is not infringing, or that you have the authorization from the copyright owner, the copyright owner&#39;s agent, or pursuant to the law, to post and use the content in your User Content, you may send a counter-notice containing the following information to our copyright agent using the contact information set forth above:</span></p><p class="c0"><span class="c1">&nbsp;</span></p><p class="c0 c3"><span class="c2">1.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">Your physical or electronic signature;</span></p><p class="c0 c3"><span class="c2">2.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A description of the content that has been removed and the location at which the content appeared before it was removed;</span></p><p class="c0 c3"><span class="c2">3.</span><span class="c2">&nbsp; &nbsp;</span><span class="c1">A statement that you have a good faith belief that the content was removed as a result of mistake or a misidentification of the content; and</span></p><p class="c0 c3"><span class="c2">4.</span><span class="c2">&nbsp; &nbsp;</span><span class="c2">Your name, address, telephone number, and email address, a statement that you consent to the jurisdiction of the federal court in </span><span class="c2"><?php echo AppSettings::nodeLegalState(); ?></span><span class="c1">&nbsp;and a statement that you will accept service of process from the person who provided notification of the alleged infringement.</span></p><p class="c0"><span class="c1">If a counter-notice is received by our copyright agent, we may send a copy of the counter-notice to the original complaining party informing such person that it may reinstate the removed content in ten (10) business days. Unless the copyright owner files an action seeking a court order against the content provider, member or user, the removed content may (in our sole discretion) be reinstated on the Site in ten (10) to fourteen (14) business days or more after receipt of the counter-notice.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">LICENSE GRANT</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">By posting any User Content via the Service, you expressly grant, and you represent and warrant that you have a right to grant, to the Company a royalty-free, sublicensable, transferable, perpetual, irrevocable, non-exclusive, worldwide license to use, reproduce, modify, publish, list information regarding, edit, translate, distribute, publicly perform, publicly display, and make derivative works of all such User Content and your name, voice, and/or likeness as contained in your User Content, if applicable, in whole or in part, and in any form, media or technology, whether now known or hereafter developed, for use in connection with the Service.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">INTELLECTUAL PROPERTY</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">You acknowledge and agree that we and our licensors retain ownership of all intellectual property rights of any kind related to the Service, including applicable copyrights, trademarks and other proprietary rights. Other product and company names that are mentioned on the Service may be trademarks of their respective owners. We reserve all rights that are not expressly granted to you under these Terms of Use.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c6">EMAIL MAY NOT BE USED TO </span><span class="c6">PROVIDE NOTICE</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">Communications made through the Service&rsquo;s email and messaging system will not constitute legal notice to the Site, the Service, or any of its officers, employees, agents or representatives in any situation where legal notice is required by contract or any law or regulation.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">USER CONSENT TO RECEIVE COMMUNICATIONS IN ELECTRONIC FORM</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">For contractual purposes, you: (a) consent to receive communications from us in an electronic form via the email address you have submitted; and (b) agree that all Terms of Use, agreements, notices, disclosures, and other communications that we provide to you electronically satisfy any legal requirement that such communications would satisfy if it were in writing. The foregoing does not affect your non-waivable rights.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">We may also use your email address to send you other messages, including information about the Site or the Service and special offers. You may opt out of such email by changing your account settings, using the &ldquo;Unsubscribe&rdquo; link in the message, or by sending an email to </span><span class="c1"><?php echo AppSettings::nodeContactEmail(); ?>.</span></p><p class="c0"><span class="c1">Opting out may prevent you from receiving messages regarding the Site, the Service or special offers.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">WARRANTY DISCLAIMER</span></p><p class="c0 c5"><span class="c4"></span></p><p class="c0"><span class="c1">THE SERVICE, IS PROVIDED &ldquo;AS IS,&rdquo; WITHOUT WARRANTY OF ANY KIND. WITHOUT LIMITING THE FOREGOING, WE EXPRESSLY DISCLAIM ALL WARRANTIES, WHETHER EXPRESS, IMPLIED OR STATUTORY, REGARDING THE SERVICE INCLUDING WITHOUT LIMITATION ANY WARRANTY OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, SECURITY, ACCURACY AND NON-INFRINGEMENT. WITHOUT LIMITING THE FOREGOING, WE MAKE NO WARRANTY OR REPRESENTATION THAT ACCESS TO OR OPERATION OF THE SERVICE WILL BE UNINTERRUPTED OR ERROR FREE. YOU ASSUME FULL RESPONSIBILITY AND RISK OF LOSS RESULTING FROM YOUR DOWNLOADING AND/OR USE OF FILES, INFORMATION, CONTENT OR OTHER MATERIAL OBTAINED FROM THE SERVICE. SOME JURISDICTIONS LIMIT OR DO NOT PERMIT DISCLAIMERS OF WARRANTY, SO THIS PROVISION MAY NOT APPLY TO YOU.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c6">LIMITATION OF DAMAGES</span><span class="c2">; </span><span class="c4">RELEASE</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">TO THE EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL THE SITE, THE SERVICE, ITS AFFILIATES, DIRECTORS, OR EMPLOYEES, OR ITS LICENSORS OR PARTNERS, BE LIABLE TO YOU FOR ANY LOSS OF PROFITS, USE, &nbsp;OR DATA, OR FOR ANY INCIDENTAL, INDIRECT, SPECIAL, CONSEQUENTIAL OR EXEMPLARY DAMAGES, HOWEVER ARISING, THAT RESULT FROM: (A) THE USE, DISCLOSURE, OR DISPLAY OF YOUR USER CONTENT; (B) YOUR USE OR INABILITY TO USE THE SERVICE; (C) THE SERVICE GENERALLY OR THE SOFTWARE OR SYSTEMS THAT MAKE THE SERVICE AVAILABLE; OR (D) ANY OTHER INTERACTIONS WITH USE OR WITH ANY OTHER USER OF THE SERVICE, WHETHER BASED ON WARRANTY, CONTRACT, TORT (INCLUDING NEGLIGENCE) OR ANY OTHER LEGAL THEORY, AND WHETHER OR NOT WE HAVE BEEN INFORMED OF THE POSSIBILITY OF SUCH DAMAGE, AND EVEN IF A REMEDY SET FORTH HEREIN IS FOUND TO HAVE FAILED OF ITS ESSENTIAL PURPOSE. SOME JURISDICTIONS LIMIT OR DO NOT PERMIT DISCLAIMERS OF LIABILITY, SO THIS PROVISION MAY NOT APPLY TO YOU.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">If you have a dispute with one or more users, a restaurant or a merchant of a product or service that you review using the Service, you release us (and our officers, directors, agents, subsidiaries, joint ventures and employees) from claims, demands and damages (actual and consequential) of every kind and nature, known and unknown, arising out of or in any way connected with such disputes. </span></p><p class="c0"><span class="c2">If you are a California resident using the Service, you may specifically waive California Civil Code &sect;1542, which says: &ldquo;A general release does not extend to claims which the creditor does not know or suspect to exist in his favor at the time of executing the release, which if known by him must have materially affected his settlement with the debtor.&rdquo;</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">MODIFICATION OF TERMS OF USE</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">We can amend these Terms of Use at any time and will update these Terms of Use in the event of any such amendments.</span><span class="c2">&nbsp;It is your sole responsibility to check the Site from time to time to view any such changes in this agreement. Your continued use of the Site or the Service signifies your agreement to our revisions to these Terms of Use.</span><span class="c2">&nbsp;</span><span class="c2">We will endeavor to notify you of material changes to the Terms by posting a notice on our homepage and/or sending an email to the email address you provided to us upon registration</span><span class="c1">. For this additional reason, you should keep your contact and profile information current. Any changes to these Terms (other than as set forth in this paragraph) or waiver of our rights hereunder shall not be valid or effective except in a written agreement bearing the physical signature of one of our officers. No purported waiver or modification of this agreement on our part via telephonic or email communications shall be valid.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c4">GENERAL TERMS</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">If any part of this Terms of Use agreement is held or found to be invalid or unenforceable, that portion of the agreement will be construed as to be consistent with applicable law while the remaining portions of the agreement will remain in full force and effect. Any failure on our part to enforce any provision of this agreement will not be considered a waiver of our right to enforce such provision. Our rights under this agreement survive any transfer or termination of this agreement.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">You agree that any cause of action related to or arising out of your relationship with the Company must commence within ONE year after the cause of action accrues. Otherwise, such cause of action is permanently barred.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">These Terms of Use and your use of the Site are governed by the federal laws of the United States of America and the laws of the State of </span><span class="c2"><?php echo AppSettings::nodeLegalState(); ?></span><span class="c1">, without regard to conflict of law provisions.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">We may assign or delegate these Terms of Service and/or our Privacy Policy, in whole or in part, to any person or entity at any time with or without your consent. You may not assign or delegate any rights or obligations under the Terms of Service or Privacy Policy without our prior written consent, and any unauthorized assignment or delegation by you is void.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c2">YOU ACKNOWLEDGE THAT YOU HAVE READ THESE TERMS OF USE, UNDERSTAND THE TERMS OF USE, AND WILL BE BOUND BY THESE TERMS AND CONDITIONS. YOU FURTHER ACKNOWLEDGE THAT THESE TERMS OF USE TOGETHER WITH THE PRIVACY POLICY AT </span><span class="c2"><a href="/privacy-policy" target="_blank">PRIVACY POLICY</a></span><span class="c1">&nbsp;REPRESENT THE COMPLETE AND EXCLUSIVE STATEMENT OF THE AGREEMENT BETWEEN US AND THAT IT SUPERSEDES ANY PROPOSAL OR PRIOR AGREEMENT ORAL OR WRITTEN, AND ANY OTHER COMMUNICATIONS BETWEEN US RELATING TO THE SUBJECT MATTER OF THIS AGREEMENT.</span></p><p class="c0 c5"><span class="c1"></span></p><p class="c0"><span class="c1">&nbsp; </span></p><p class="c0 c5"><span class="c1"></span></p><div><p class="c10"><span class="c2 c7">Terms of Use</span></p><p class="c5 c8"><span class="c7 c2"></span></p></div>
		
		</div>
	</div>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>