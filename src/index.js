import Reports from './Reports';

wp.element.render(<Reports />, document.querySelector( '.entry-content' ) );

/**
 * Internal block libraries
 */
// const { registerBlockType } = wp.blocks;

/**
 * Register reports block
 */
// export default registerBlockType("gccustom/ta-reports", {
// 	title: "TA Reports Table",
// 	description: "Displays user progress report for Thinker Academy",
// 	category: "common",
// 	icon: "welcome-widgets-menus",
// 	keywords: ["reports", "ta", "table"],
// 	supports: {html: false},
// 	attributes: {},
// 	edit: () => (<div class='ta-reports-block alignwide'><Reports /></div>),
// 	save: () => (<div class='ta-reports-block alignwide'><Reports /></div>)
// });
