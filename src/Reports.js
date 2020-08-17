const { Component } = wp.element;
import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import TableCell from '@material-ui/core/TableCell';
import TableHead from '@material-ui/core/TableHead';
import TableRow from '@material-ui/core/TableRow';

class Reports extends Component {
	/*
	 * takes in an object and returns it as an array
	*/
	genArray = (dataObj) => {
		const dataArray = [];
		const keys = Object.keys(dataObj);
		keys.forEach((key) => {
			dataArray.push(dataObj[key]);
		})
		return dataArray;
	}

	/*
	 * generates the subtitle row
	*/
	genSubtitle = (nCols) => {
		let rows = []
		for(let i=0;i<nCols;i++) {
			if(i>3) {
				if(i%2) {
					rows.push("Attempts")
				} else {
					rows.push("Score")
				}
			} else {
				rows.push("")
			}
		}
		return rows;
	}

	/*
	 * takes in an array and an object, flattens the object, and adds it to the array
	*/
	collapseScores = (rowArray,scoresObj) => {
		const keys = Object.keys(scoresObj);
		keys.forEach((key) => {
			rowArray.push(scoresObj[key]['score']);
			rowArray.push(scoresObj[key]['attempts']);
		});
		return rowArray;
	}

	/*
	 * calls genArray() and collapseScores(), then generates a row from the new array
	*/
	genRow = (rowObj) => {
		let rowArray = this.genArray(rowObj);	// generate an array of the row data
		const scoresObj = rowArray[4];	// get the scores object
		rowArray.splice(4,1);	// remove the scores object from the array
		rowArray = this.collapseScores(rowArray,scoresObj);	// collapse the scores object and add it to the array
		return rowArray.map((cell,i) => i==3 ? <TableCell>{cell}%</TableCell> : <TableCell>{cell}</TableCell>);
	}

	render() {
		const userArray = this.genArray(repObj.usersData);	// generate an array of all the users
		return (
			<div>
				<Table size="small">
					<TableHead>
						<TableRow>
							{repObj.sectionHeaders.map((header,i) => {
								if(i>3) {
									return <TableCell colSpan={2}>{header}</TableCell>
								}
								return <TableCell>{header}</TableCell>
							})}
						</TableRow>
						<TableRow>
							{this.genSubtitle(16).map((subtitle) => (
								<TableCell>{subtitle}</TableCell>
							))}
						</TableRow>
					</TableHead>
					<TableBody>
						{userArray.map((user) => (
							<TableRow>
								{this.genRow(user)}
							</TableRow>
						))}
					</TableBody>
				</Table>
			</div>
		);
	}
}

export default Reports;
