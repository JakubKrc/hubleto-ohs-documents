import React from 'react';
import Translator from '@hubleto/react-ui/core/Translator';
import Form, { FormMetaContext } from '@hubleto/react-ui/components/fc/Form';
import { type FormProps } from '@hubleto/react-ui/components/fc/FormInterfaces';
import Input from '@hubleto/react-ui/components/fc/FormComponents/Input';
import { useRecordField } from '@hubleto/react-ui/components/fc/FormRecordStore';

const componentName = 'FormDocument';
const parentApp = 'Hubleto/App/External/WaiBlue/OhsDocuments';
const T = new Translator(parentApp + '/Loader', 'Components/' + componentName);

// Hubleto's form has no input for a virtual column, so the client and what the document belongs to are shown as text.
const LinkedRecordTexts = () => {
  const customerName = useRecordField<string>('virt_ohs_customer', '');
  const linkedRecordText = useRecordField<string>('virt_ohs_linked_record', '');
  return <div className='flex flex-col gap-2 mb-2'>
    <div>
      <div className='text-sm text-gray-500'>{T.translate('Client')}</div>
      <div>{customerName}</div>
    </div>
    <div>
      <div className='text-sm text-gray-500'>{T.translate('Belongs to')}</div>
      <div>{linkedRecordText}</div>
    </div>
  </div>;
}

// What the document belongs to comes from the tab it was added in; the server keeps it, the author and the date.
const TabDefault = () => {
  const form = React.useContext(FormMetaContext);
  const isSaved = form.id > 0;
  return <div className='grid grid-cols-1 md:grid-cols-2 gap-4'>
    <div>
      <Input field='name' />
      {isSaved ? <LinkedRecordTexts /> : null}
      <Input field='ohs_file' />
    </div>
    <div>
      <Input field='ohs_priority' />
      {isSaved ? <Input field='created_on' /> : null}
      {isSaved ? <Input field='id_created_by' /> : null}
    </div>
  </div>;
}

// Hubleto's document (model Hubleto/App/Community/Documents/Models/Document) as a work document.
const FormDocument = (props: FormProps) => <Form
  componentName={componentName}
  parentApp={parentApp}
  model='Hubleto/App/Community/Documents/Models/Document'
  urlSlug='ohs/documents/documents'
  title={{field: 'name', sub: T.translate('Work document')}}
  tabs={{default: {content: () => <TabDefault />}}}
  {...props}
/>;

export default FormDocument;
