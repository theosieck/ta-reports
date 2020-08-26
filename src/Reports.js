const { Component } = wp.element;
import Button from '@material-ui/core/Button';
import ReactHtmlParser from 'react-html-parser';
import MaterialTable from "material-table";
import { forwardRef } from 'react';
import AddBox from '@material-ui/icons/AddBox';
import ArrowDownward from '@material-ui/icons/ArrowDownward';
import Check from '@material-ui/icons/Check';
import ChevronLeft from '@material-ui/icons/ChevronLeft';
import ChevronRight from '@material-ui/icons/ChevronRight';
import Clear from '@material-ui/icons/Clear';
import DeleteOutline from '@material-ui/icons/DeleteOutline';
import Edit from '@material-ui/icons/Edit';
import FilterList from '@material-ui/icons/FilterList';
import FirstPage from '@material-ui/icons/FirstPage';
import LastPage from '@material-ui/icons/LastPage';
import Remove from '@material-ui/icons/Remove';
import SaveAlt from '@material-ui/icons/SaveAlt';
import Search from '@material-ui/icons/Search';
import ViewColumn from '@material-ui/icons/ViewColumn';

const tableIcons = {
    Add: forwardRef((props, ref) => <AddBox {...props} ref={ref} />),
    Check: forwardRef((props, ref) => <Check {...props} ref={ref} />),
    Clear: forwardRef((props, ref) => <Clear {...props} ref={ref} />),
    Delete: forwardRef((props, ref) => <DeleteOutline {...props} ref={ref} />),
    DetailPanel: forwardRef((props, ref) => <ChevronRight {...props} ref={ref} />),
    Edit: forwardRef((props, ref) => <Edit {...props} ref={ref} />),
    Export: forwardRef((props, ref) => <SaveAlt {...props} ref={ref} />),
    Filter: forwardRef((props, ref) => <FilterList {...props} ref={ref} />),
    FirstPage: forwardRef((props, ref) => <FirstPage {...props} ref={ref} />),
    LastPage: forwardRef((props, ref) => <LastPage {...props} ref={ref} />),
    NextPage: forwardRef((props, ref) => <ChevronRight {...props} ref={ref} />),
    PreviousPage: forwardRef((props, ref) => <ChevronLeft {...props} ref={ref} />),
    ResetSearch: forwardRef((props, ref) => <Clear {...props} ref={ref} />),
    Search: forwardRef((props, ref) => <Search {...props} ref={ref} />),
    SortArrow: forwardRef((props, ref) => <ArrowDownward {...props} ref={ref} />),
    ThirdStateCheck: forwardRef((props, ref) => <Remove {...props} ref={ref} />),
    ViewColumn: forwardRef((props, ref) => <ViewColumn {...props} ref={ref} />)
  };

class Reports extends Component {
	/**
	 * takes in a row and formats it into an object with k=>v pairs for each cell
	 * called in genRows()
	*/
	formatData = (row) => {
		let formattedRow = {};
		const keys = Object.keys(row);
		keys.map((key) => {
			const cell = row[key];
			if(typeof(cell) != 'object') {
				formattedRow[key] = cell;
			} else {
				formattedRow[key] = ReactHtmlParser(`<div class="sub"><span>${cell['score']}</span><span>${cell['attempts']}</span></div>`);
			}
		});
		return formattedRow;
	}

	/*
	 * generates an array of row objects where each object has a key=>value pair with the data for each column
	 * called in render()
	*/
	genRows = () => {
		let data = [];
		const usersData = repObj.usersData;
		const keys = Object.keys(usersData);
		keys.map((key) => {
			data.push(this.formatData(usersData[key]));
		})
		return data;
	}

	/*
	 * generates an array of column objects where each object has a title and field
	 * "title" = column title, "field" = column id (matches the tag in userData)
	 * called in render()
	*/
	genColumns = () => {
		let columns = [];
		const headers = repObj.sectionHeaders;
		const titles = Object.keys(headers);
		titles.map((title,i) => {
			let titleObj = {};
			if(i>3) {
				titleObj = {title:ReactHtmlParser(`${title}<div class="sub"><span>Score</span><span>Attempts</span></div>`),field:headers[title].toString()};
			} else {
				titleObj = {title,field:headers[title].toString()};
			}
			
			columns.push(titleObj);
		});
		return columns;
	}

	/**
	 * preps the data & opens a download dialogue to save as CSV
	 * called in render() when the csv download button is clicked
	*/
	handleDownload() {
		const columns = Object.keys(repObj.sectionHeaders);
		const data = repObj.usersData;

		const filename = 'Study Skills Report.csv';
		let content = '';

		// format columns and add to content string -> each column is a cell in one row
		columns.forEach((column,i) => {
			// the score/attempts columns are all arrays, the others are just strings
			if(i>3) {
				content += `${column} Score,${column} Attempts${column==='Final Challenge' ? '\n' : ','}`;
			} else {
				content += column + ',';
			}
		});

		// format data and add to content string -> each element in data is a row
		const userIDs = Object.keys(data);	// 1 row = 1 user
		userIDs.forEach((id) => {
			const row = data[id];
			const keys = Object.keys(row);	// 0 final, 1-5 scores, 6-9 username etc (in correct order)
			// 'normal' cells
			for(let i=6;i<10;i++) {
				const cell = row[keys[i]].toString();
				console.log(typeof(cell));
				content += cell.replace(/,/g, '') + ',';	// escape any commas
			}
			for(let i=1;i<6;i++) {
				const dataPair = row[keys[i]];
				content += `${dataPair.score},${dataPair.attempts},`;
			}
			const finalChallenge = row[keys[0]];
			content += `${finalChallenge.score},${finalChallenge.attempts}\n`;
		})

		// prepare & download file
		var element = document.createElement('a');
		element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(content));
		element.setAttribute('download', filename);
		element.style.display = 'none';
		document.body.appendChild(element);
		element.click();
		document.body.removeChild(element);
	}

	render() {
		const columns = this.genColumns();
		const data = this.genRows();

		return (
			<div>
			<MaterialTable
				columns={columns}
				data={data}
				title={<Button className="ta-home-button" href={repObj.courseUrl}><span class="dashicons dashicons-admin-home"></span></Button>}
				options={{
					exportButton:true,
					exportFileName:"Study Skills Report",
					exportCsv: (columns, data) => this.handleDownload(),
					fixedColumns: {
		        left: 1,
		        right: 0
		      }
				}}
				icons={tableIcons}
			/>
			</div>
		);
	}
}

export default Reports;
