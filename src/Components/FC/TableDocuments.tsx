import Table from '@hubleto/react-ui/components/fc/Table'
import { type TableMeta, type TableProps } from '@hubleto/react-ui/components/fc/TableInterfaces'
import FormDocument from './FormDocument'

interface TableDocumentsProps extends TableProps {
  linkedModel?: string,
  linkedRecordId?: number,
}

const componentName = 'TableDocuments'
const parentApp = 'Hubleto/App/External/WaiBlue/OhsDocuments'

// Hubleto's documents: the work documents list, or a tab of a client, branch or audit form (linked by model + record id).
const TableDocuments = (props: TableDocumentsProps) => {
  const linkedModel = props.linkedModel ?? '';
  const linkedRecordId = props.linkedRecordId ?? 0;
  const isTabOfRecord = linkedModel != '' && linkedRecordId > 0;

  const endpointParams: Record<string, any> = {ohsDocuments: !isTabOfRecord};
  const formDefaultValues: Record<string, any> = {};
  if (isTabOfRecord) {
    endpointParams.linkedModel = linkedModel;
    endpointParams.linkedRecordId = linkedRecordId;
    formDefaultValues.model = linkedModel;
    formDefaultValues.record_id = linkedRecordId;
  }

  return <Table
    componentName={componentName}
    parentApp={parentApp}
    model='Hubleto/App/Community/Documents/Models/Document'
    baseUrlSlug='ohs/documents/documents'
    endpointParams={endpointParams}
    formDefaultValues={formDefaultValues}
    formModalProps={{type: 'right wide'}}
    renderForm={(table: TableMeta) => <FormDocument {...table.getDefaultFormProps()} />}
    {...props}
  />;
}

export default TableDocuments;
