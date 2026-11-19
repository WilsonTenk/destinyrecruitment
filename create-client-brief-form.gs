/**
 * Creates the "Website Project – Client Brief" Google Form.
 *
 * How to use:
 *   1. Go to https://script.google.com and click "New project".
 *   2. Delete the sample code, paste this whole file in, and click Save.
 *   3. Pick the function "createClientBriefForm" in the toolbar and click Run.
 *   4. Approve the permissions Google asks for (it's your own script).
 *   5. Open "Execution log": it prints the edit link and the link to send the client.
 *      The form is also saved in your Google Drive.
 */
function createClientBriefForm() {
  var form = FormApp.create('Website Project – Client Brief');
  form.setDescription(
    'Thank you for choosing us to build your website. This questionnaire helps us understand ' +
    'your business, your goals and what the website needs to do. It takes about 10–15 minutes. ' +
    'If you are not sure about an answer, write "not sure" and we will discuss it together.'
  );
  form.setCollectEmail(true);
  form.setProgressBar(true);
  form.setConfirmationMessage('Thank you! We have received your answers and will contact you shortly to discuss next steps.');

  // ---------- Contact ----------
  form.addSectionHeaderItem().setTitle('Contact details');
  form.addTextItem().setTitle('Your full name').setRequired(true);
  form.addTextItem().setTitle('Business / company name').setRequired(true);
  form.addTextItem().setTitle('Phone / WhatsApp number').setRequired(true);
  form.addTextItem().setTitle('Your role in the business (e.g. owner, manager)');

  // ---------- 1. Business & goals ----------
  form.addPageBreakItem().setTitle('1. Your business and goals');
  form.addParagraphTextItem()
    .setTitle('What does your business do, and in which city / areas do you operate?')
    .setRequired(true);
  form.addCheckboxItem()
    .setTitle('What is the MAIN thing you want visitors to do on the website?')
    .setChoiceValues([
      'Book via WhatsApp',
      'Call us directly',
      'Fill in a booking / contact form',
      'Book and pay online',
      'Learn about us (information only)'
    ])
    .showOtherOption(true)
    .setRequired(true);
  form.addParagraphTextItem()
    .setTitle('Who are your customers? (e.g. families, expats, companies, age, location)')
    .setRequired(true);
  form.addCheckboxItem()
    .setTitle('How will you measure if the website is successful?')
    .setChoiceValues([
      'More bookings / enquiries',
      'More phone calls',
      'Better ranking on Google',
      'Looking more professional and trustworthy'
    ])
    .showOtherOption(true);
  form.addParagraphTextItem()
    .setTitle('Who are your main competitors, and what makes your business different from them?');

  // ---------- 2. Features ----------
  form.addPageBreakItem().setTitle('2. Features the website needs');
  form.addMultipleChoiceItem()
    .setTitle('Should customers be able to book a date and time slot directly on the website?')
    .setChoiceValues(['Yes', 'No – WhatsApp / phone is enough', 'Not sure'])
    .setRequired(true);
  form.addMultipleChoiceItem()
    .setTitle('Do you want an admin panel to update workers, prices and availability yourself?')
    .setChoiceValues(['Yes', 'No – the developer can update it', 'Not sure'])
    .setRequired(true);
  form.addMultipleChoiceItem()
    .setTitle('Should the website show live availability for each worker (e.g. "Available now" / "Booked")?')
    .setChoiceValues(['Yes', 'No', 'Not sure']);
  form.addCheckboxItem()
    .setTitle('Which payment methods do you accept or want to offer?')
    .setChoiceValues([
      'Cash',
      'Bank transfer',
      'STC Pay',
      'Mada / card payment online',
      'Apple Pay',
      'Buy now, pay later (Tabby / Tamara)'
    ])
    .showOtherOption(true);
  form.addMultipleChoiceItem()
    .setTitle('Do customers need accounts (login, booking history, reviews)?')
    .setChoiceValues(['Yes', 'No', 'Not sure']);
  form.addCheckboxItem()
    .setTitle('How should you be notified of new bookings?')
    .setChoiceValues(['WhatsApp', 'SMS', 'Email', 'Admin dashboard'])
    .showOtherOption(true);
  form.addCheckboxItem()
    .setTitle('Any tools the website should connect to?')
    .setChoiceValues([
      'WhatsApp Business',
      'Google Maps',
      'Google Analytics',
      'Google Sheets',
      'CRM system',
      'None / not sure'
    ])
    .showOtherOption(true);

  // ---------- 3. Content ----------
  form.addPageBreakItem().setTitle('3. Content');
  form.addMultipleChoiceItem()
    .setTitle('Which languages should the website be in?')
    .setChoiceValues(['Arabic only', 'English only', 'Arabic and English (Arabic default)', 'Arabic and English (English default)'])
    .showOtherOption(true)
    .setRequired(true);
  form.addMultipleChoiceItem()
    .setTitle('Who will write the text for the website?')
    .setChoiceValues(['We will provide it', 'We want you to write it', 'A mix of both'])
    .setRequired(true);
  form.addMultipleChoiceItem()
    .setTitle('Can you provide real photos and details (name, experience, skills) for worker profiles?')
    .setChoiceValues([
      'Yes, and the workers have agreed to be shown',
      'Yes, but we need to get their permission first',
      'No – do not show individual workers',
      'Not sure'
    ]);
  form.addParagraphTextItem()
    .setTitle('List your services and prices (hourly and monthly packages). Should prices be shown publicly?');
  form.addParagraphTextItem()
    .setTitle('Which cities and neighbourhoods do you serve? Do you have more than one office / branch?');
  form.addCheckboxItem()
    .setTitle('Which of these can you provide to build trust?')
    .setChoiceValues([
      'Commercial registration / licence number',
      'Customer testimonials / reviews',
      'Frequently asked questions',
      'Guarantee or replacement policy',
      'Office address and map location'
    ])
    .showOtherOption(true);

  // ---------- 4. Project logistics ----------
  form.addPageBreakItem().setTitle('4. Budget and timeline');
  form.addTextItem()
    .setTitle('What is your budget range for this website? (state the currency)')
    .setRequired(true);
  form.addDateItem().setTitle('When do you need the website live?');
  form.addParagraphTextItem().setTitle('Is there a reason for this deadline (launch, campaign, season)?');
  form.addTextItem().setTitle('Who will approve the designs and final website? (name and role)');
  form.addCheckboxItem()
    .setTitle('Are you interested in any services after launch?')
    .setChoiceValues(['Hosting', 'Maintenance and updates', 'Google ads / social media marketing', 'SEO', 'None for now'])
    .showOtherOption(true);
  form.addParagraphTextItem().setTitle('Anything else we should know?');

  Logger.log('Edit the form:   ' + form.getEditUrl());
  Logger.log('Send to client:  ' + form.getPublishedUrl());
}
