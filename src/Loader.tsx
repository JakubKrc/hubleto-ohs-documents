import React from 'react';
import App from '@hubleto/react-ui/core/App'
import Translator from '@hubleto/react-ui/core/Translator';
import FormCustomizer from '@hubleto/react-ui/core/FormCustomizer';
import { type FormMeta } from '@hubleto/react-ui/components/fc/FormInterfaces';
import TableDocuments from './Components/FC/TableDocuments'

const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrDocuments';
const T = new Translator(parentApp + '/Loader', 'Loader');

// "Dokumenty" tabs: the documents linked directly to the client, the branch or the audit (Hubleto's model + record id).
const CUSTOMER_MODEL = 'Hubleto/App/Community/Customers/Models/Customer';
const BRANCH_MODEL = 'Hubleto/App/External/WaiBlue/HlthcrBranches/Models/Branch';
const AUDIT_MODEL = 'Hubleto/App/External/WaiBlue/HlthcrAudits/Models/Audit';

function mountDocumentsTab(linkedModel: string, saveFirstText: string) {
  return {
    title: T.translate('Documents'),
    content: (form: FormMeta) => {
      if (form.id <= 0) return <div className='alert alert-info'>{saveFirstText}</div>;
      return <TableDocuments
        uid={form.uid + '_table_documents'}
        parentForm={form}
        linkedModel={linkedModel}
        linkedRecordId={form.id}
      />;
    },
  };
}

function mountCustomerDocumentsTab() {
  return mountDocumentsTab(CUSTOMER_MODEL, T.translate('Save the client first.'));
}

function mountBranchDocumentsTab() {
  return mountDocumentsTab(BRANCH_MODEL, T.translate('Save the branch first.'));
}

function mountAuditDocumentsTab() {
  return mountDocumentsTab(AUDIT_MODEL, T.translate('Save the audit first.'));
}

class HlthcrDocumentsApp extends App {
  init() {
    super.init();
    globalThis.hubleto.registerReactComponent('HlthcrDocumentsTableDocuments', TableDocuments);

    FormCustomizer.addTab('FormCustomer', 'hlthcr-documents', mountCustomerDocumentsTab);
    FormCustomizer.addTab('FormBranch', 'hlthcr-documents', mountBranchDocumentsTab);
    FormCustomizer.addTab('FormAudit', 'hlthcr-documents', mountAuditDocumentsTab);
  }
}

globalThis.hubleto.registerApp(parentApp, new HlthcrDocumentsApp());
